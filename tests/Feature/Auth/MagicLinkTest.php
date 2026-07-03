<?php

declare(strict_types=1);

use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Services\Auth\MagicLinkService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * C12.8a — Connexion membre par magic link (PRD §3.2 / ADR-0011).
 */

/** Extrait l'URL signée du MagicLinkMail mis en queue (le plus récent). */
function queuedMagicLinkUrl(): string
{
    $url = null;

    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url): bool {
        $url = $mail->url;

        return true;
    });

    return (string) $url;
}

/** Extrait le jeton en clair depuis l'URL signée du lien. */
function magicLinkTokenFromUrl(string $url): string
{
    preg_match('#/magic-link/([^/?]+)#', $url, $matches);

    return $matches[1] ?? '';
}

it('envoie un email avec un lien signé au membre actif, jeton hashé en DB (jamais en clair)', function () {
    Mail::fake();
    $user = User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();

    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('membre@ecoworking.fr'));

    $url = queuedMagicLinkUrl();
    $token = magicLinkTokenFromUrl($url);

    expect($url)->toContain('signature=')
        ->and($token)->not->toBe('')
        // Le jeton en clair n'est JAMAIS persisté — seul son hash SHA-256 l'est.
        ->and(MagicLinkToken::where('token_hash', $token)->exists())->toBeFalse()
        ->and(MagicLinkToken::where('token_hash', hash('sha256', $token))->where('user_id', $user->id)->exists())->toBeTrue();
});

it('répond strictement la même chose que l\'email existe ou non (anti-énumération)', function () {
    Mail::fake();
    User::factory()->member()->create(['email' => 'existe@ecoworking.fr']);

    $existing = postJson('/magic-link', ['email' => 'existe@ecoworking.fr']);
    $missing = postJson('/magic-link', ['email' => 'inconnu@ecoworking.fr']);

    expect($missing->getStatusCode())->toBe($existing->getStatusCode())
        ->and($missing->json())->toBe($existing->json());

    // Aucun email n'est parti pour l'adresse inconnue (et aucun jeton créé).
    Mail::assertNotQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('inconnu@ecoworking.fr'));
    expect(MagicLinkToken::count())->toBe(1);
});

it('n\'envoie JAMAIS de magic link à un compte admin (réponse générique identique)', function () {
    Mail::fake();
    User::factory()->admin()->create(['email' => 'admin@ecoworking.fr']);
    User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    $admin = postJson('/magic-link', ['email' => 'admin@ecoworking.fr'])->assertOk();
    $member = postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();

    // Même réponse pour l'admin que pour un membre : rien d'exploitable.
    expect($admin->json())->toBe($member->json());

    Mail::assertNotQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('admin@ecoworking.fr'));
    expect(MagicLinkToken::whereHas('user', fn ($q) => $q->where('email', 'admin@ecoworking.fr'))->count())->toBe(0);
});

it('connecte la session membre via le lien puis /api/user répond', function () {
    Mail::fake();
    $user = User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    $url = queuedMagicLinkUrl();

    get($url)->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    getJson('/api/user')->assertOk()->assertJsonPath('email', 'membre@ecoworking.fr');

    // Jeton consommé (usage unique).
    expect(MagicLinkToken::whereNull('used_at')->count())->toBe(0);
});

it('refuse la seconde utilisation du même lien (usage unique)', function () {
    Mail::fake();
    User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    $url = queuedMagicLinkUrl();

    get($url)->assertRedirect('/');
    post('/logout');

    get($url)->assertRedirect('/login?magic_link=invalid');
    $this->assertGuest();
});

it('refuse un lien expiré (15 minutes)', function () {
    Mail::fake();
    User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    $url = queuedMagicLinkUrl();

    $this->travel(16)->minutes();

    get($url)->assertRedirect('/login?magic_link=invalid');
    $this->assertGuest();
});

it('refuse un lien altéré (signature invalide) sans consommer le jeton', function () {
    Mail::fake();
    User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    $url = queuedMagicLinkUrl();
    $token = magicLinkTokenFromUrl($url);

    // Jeton substitué : la signature ne couvre plus l'URL → refus.
    $tampered = str_replace($token, bin2hex(random_bytes(32)), $url);

    get($tampered)->assertRedirect('/login?magic_link=invalid');
    $this->assertGuest();
    expect(MagicLinkToken::whereNull('used_at')->count())->toBe(1);
});

it('ne connecte pas un admin même avec un lien forgé valide (pas d\'escalade)', function () {
    $admin = User::factory()->admin()->create();

    // Lien forgé « parfait » (jeton en DB + signature valide) pour un admin :
    // l'éligibilité est revérifiée à la consommation → refus.
    $token = bin2hex(random_bytes(32));
    MagicLinkToken::create([
        'user_id' => $admin->id,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(MagicLinkService::TTL_MINUTES),
    ]);
    $url = URL::temporarySignedRoute('magic-link.consume', now()->addMinutes(15), ['token' => $token]);

    get($url)->assertRedirect('/login?magic_link=invalid');
    $this->assertGuest();

    // Et le jeton est tout de même consommé : il ne resservira pas.
    expect(MagicLinkToken::whereNull('used_at')->count())->toBe(0);
});

it('invalide les jetons au changement de mot de passe', function () {
    Mail::fake();
    $user = User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    $url = queuedMagicLinkUrl();
    expect(MagicLinkToken::where('user_id', $user->id)->count())->toBe(1);

    // Tout chemin de changement de mdp passe par le modèle (observer C12.8a).
    $user->forceFill(['password' => Hash::make('nouveau-mot-de-passe')])->save();

    expect(MagicLinkToken::where('user_id', $user->id)->count())->toBe(0);

    get($url)->assertRedirect('/login?magic_link=invalid');
    $this->assertGuest();
});

it('rate-limite la demande de magic link (429 au-delà de 5/min)', function () {
    Mail::fake();
    User::factory()->member()->create(['email' => 'membre@ecoworking.fr']);

    foreach (range(1, 5) as $i) {
        postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertOk();
    }

    postJson('/magic-link', ['email' => 'membre@ecoworking.fr'])->assertTooManyRequests();
});

it('valide l\'email de la demande (422)', function () {
    postJson('/magic-link', ['email' => 'pas-un-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

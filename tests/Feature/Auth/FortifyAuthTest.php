<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\postJson;

/** C2.1 — Auth admin/portail via Fortify (sessions + 2FA TOTP). BRIEF §8 / PRD §3.2. */
it('connecte un utilisateur avec des identifiants valides (POST /login)', function () {
    $user = User::factory()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/login', ['email' => 'membre@ecoworking.fr', 'password' => 'password'])
        ->assertSuccessful();

    $this->assertAuthenticatedAs($user);
});

it('rejette un mauvais mot de passe sans authentifier (422)', function () {
    User::factory()->create(['email' => 'membre@ecoworking.fr']);

    postJson('/login', ['email' => 'membre@ecoworking.fr', 'password' => 'mauvais'])
        ->assertStatus(422);

    $this->assertGuest();
});

it('ne révèle pas si l\'email existe (même message d\'erreur)', function () {
    User::factory()->create(['email' => 'existe@ecoworking.fr']);

    $existing = postJson('/login', ['email' => 'existe@ecoworking.fr', 'password' => 'faux'])
        ->assertStatus(422)->json('errors.email.0');
    $missing = postJson('/login', ['email' => 'inconnu@ecoworking.fr', 'password' => 'faux'])
        ->assertStatus(422)->json('errors.email.0');

    expect($existing)->toBe($missing);
});

it('désactive l\'inscription self-service (PRD §3.2)', function () {
    // 405 (et non 404) depuis le catch-all SPA (C12.1) : l'URI /register
    // matche le GET catch-all du portail, mais aucune route POST n'existe —
    // la feature Fortify reste bien désactivée.
    postJson('/register', [
        'name' => 'X', 'email' => 'x@ecoworking.fr',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertMethodNotAllowed();
});

it('désactive la mise à jour de profil Fortify (flux dédiés : /api/profile, PRD §3.4.5)', function () {
    $user = User::factory()->create();

    // L'action scaffoldée écrivait une colonne `name` inexistante et aurait
    // permis un changement d'email libre, exclu du MVP — feature désactivée.
    // 405 (et non 404) depuis le catch-all SPA (C12.1) : l'URI matche le GET
    // catch-all du portail, mais aucune route PUT n'existe.
    $this->actingAs($user)
        ->putJson('/user/profile-information', [
            'name' => 'Nouveau Nom', 'email' => 'nouvel-email@ecoworking.fr',
        ])->assertMethodNotAllowed();
});

it('permet d\'activer le 2FA TOTP pour un compte (Fortify)', function () {
    $user = User::factory()->create();

    expect($user->two_factor_secret)->toBeNull();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->postJson('/user/two-factor-authentication')
        ->assertSuccessful();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        // confirm=true : le secret est posé mais pas encore confirmé.
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('exige le challenge 2FA au login puis authentifie avec un code TOTP valide', function () {
    $engine = app(Google2FA::class);
    $secret = $engine->generateSecretKey();
    $user = User::factory()->create([
        'email' => 'totp@ecoworking.fr',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_confirmed_at' => now(),
    ]);

    // Login : identifiants valides → PAS de session, redirection vers le challenge.
    postJson('/login', ['email' => 'totp@ecoworking.fr', 'password' => 'password'])
        ->assertSuccessful()
        ->assertJson(['two_factor' => true]);
    $this->assertGuest();

    // Challenge avec le code TOTP courant (généré par le provider du package).
    postJson('/two-factor-challenge', ['code' => $engine->getCurrentOtp($secret)])
        ->assertSuccessful();

    $this->assertAuthenticatedAs($user);
});

it('rejette un code TOTP invalide au challenge 2FA (session non ouverte)', function () {
    $engine = app(Google2FA::class);
    User::factory()->create([
        'email' => 'totp@ecoworking.fr',
        'two_factor_secret' => encrypt($engine->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_confirmed_at' => now(),
    ]);
    postJson('/login', ['email' => 'totp@ecoworking.fr', 'password' => 'password'])
        ->assertJson(['two_factor' => true]);

    postJson('/two-factor-challenge', ['code' => '000000'])->assertStatus(422);

    $this->assertGuest();
});

it('authentifie via un recovery code au challenge 2FA (et le consomme)', function () {
    $engine = app(Google2FA::class);
    $user = User::factory()->create([
        'email' => 'totp@ecoworking.fr',
        'two_factor_secret' => encrypt($engine->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345', 'fghij-67890'])),
        'two_factor_confirmed_at' => now(),
    ]);
    postJson('/login', ['email' => 'totp@ecoworking.fr', 'password' => 'password'])
        ->assertJson(['two_factor' => true]);

    postJson('/two-factor-challenge', ['recovery_code' => 'abcde-12345'])
        ->assertSuccessful();

    $this->assertAuthenticatedAs($user);
    // Usage unique : le code consommé est remplacé dans la liste.
    expect(json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true))
        ->not->toContain('abcde-12345');
});

it('déconnecte et détruit la session (POST /logout)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/logout')->assertSuccessful();

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Lot F — Changement de mot de passe portail (PRD §3.4.2 / §3.4.5)
|--------------------------------------------------------------------------
|
| `PUT /user/password` (Fortify, feature `updatePasswords`) : ré-authentification
| obligatoire (mot de passe actuel), réponses JSON 200/422 pour la SPA.
*/

it('change le mot de passe avec le mot de passe actuel et une confirmation valides', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])->assertSuccessful();

    expect(Hash::check('nouveau-mot-de-passe-2026', $user->fresh()->password))->toBeTrue();
});

it('rejette un changement de mot de passe si le mot de passe actuel est faux (422)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'mauvais-mot-de-passe',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejette un changement de mot de passe si la confirmation diffère (422)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'autre-chose',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejette un changement de mot de passe trop court (422, politique Password::default = min 8)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'court1',
        'password_confirmation' => 'court1',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('refuse le changement de mot de passe à un visiteur anonyme (401)', function () {
    $this->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Révocation des autres sessions au changement de mot de passe
|--------------------------------------------------------------------------
|
| `Auth::logoutOtherDevices()` re-hash le mot de passe : l'empreinte
| `password_hash_web` mémorisée par les sessions ouvertes ailleurs devient
| caduque, et AuthenticateSession (bootstrap/app.php) les rejette.
*/

/**
 * Rejoue une requête portail « comme le navigateur » : c'est l'en-tête Referer
 * qui fait basculer Sanctum en mode stateful (session + cookies). Sans lui, une
 * requête /api/* de test n'ouvre aucune session et le middleware passe la main.
 */
function asPortalBrowser(): void
{
    config(['sanctum.stateful' => ['localhost']]);
}

it('révoque les autres sessions au changement de mot de passe', function () {
    asPortalBrowser();
    $user = User::factory()->resident()->create();
    // Empreinte qu'une session ouverte AILLEURS a mémorisée à sa connexion.
    $hashKnownByTheOtherSession = $user->password;

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])->assertSuccessful();

    // Le hash en base a bien changé : l'empreinte de l'autre session est périmée.
    expect($user->fresh()->password)->not->toBe($hashKnownByTheOtherSession);

    $this->flushSession();
    $this->withSession(['password_hash_web' => $hashKnownByTheOtherSession])
        ->actingAs($user)
        ->withHeader('Referer', 'http://localhost')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('laisse la session courante active après son propre changement de mot de passe', function () {
    asPortalBrowser();
    $user = User::factory()->resident()->create();

    $this->actingAs($user)->putJson('/user/password', [
        'current_password' => 'password',
        'password' => 'nouveau-mot-de-passe-2026',
        'password_confirmation' => 'nouveau-mot-de-passe-2026',
    ])->assertSuccessful();

    $this->withSession(['password_hash_web' => $user->fresh()->password])
        ->actingAs($user->fresh())
        ->withHeader('Referer', 'http://localhost')
        ->getJson('/api/user')
        ->assertOk();
});

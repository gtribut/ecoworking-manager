<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Services\Auth\WelcomeInvitationService;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

/**
 * Lot F — Email d'accueil à la création d'un compte (PRD §3.2).
 *
 * L'admin ne choisit plus de mot de passe : le membre reçoit un lien de
 * définition (broker `welcome`, table dédiée, 3 jours) qui se consomme par le
 * flux Fortify sur `POST /reset-password/welcome`.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Mail::fake();
});

/** URL du lien d'accueil capturée dans le mail envoyé à cet email. */
function welcomeUrlSentTo(string $email): string
{
    $url = null;

    Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) use ($email, &$url): bool {
        if (! $mail->hasTo($email)) {
            return false;
        }

        $url = $mail->url;

        return true;
    });

    return (string) $url;
}

/** Jeton brut extrait de l'URL `/reset-password/{token}?…`. */
function tokenFromUrl(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);

    return basename($path);
}

it('n\'expose plus de champ mot de passe dans le formulaire admin', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)->assertFormFieldDoesNotExist('password');
});

it('crée le compte sans mot de passe saisi et envoie l\'email d\'accueil', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.test',
            'roles' => [SpatieRole::findByName(Role::Resident->value, 'web')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'jean.dupont@example.test')->firstOrFail();

    $url = welcomeUrlSentTo('jean.dupont@example.test');
    expect($url)->toContain('/reset-password/')
        ->and($url)->toContain('welcome=1')
        ->and($url)->toContain(urlencode('jean.dupont@example.test'));

    // Le jeton envoyé est bien celui du broker d'accueil (table dédiée).
    expect(Password::broker(WelcomeInvitationService::BROKER)->tokenExists($user, tokenFromUrl($url)))->toBeTrue()
        ->and(DB::table('welcome_invitation_tokens')->where('email', $user->email)->exists())->toBeTrue()
        // Et PAS dans le dépôt « mot de passe oublié » : flux étanches.
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('ne met jamais le mot de passe en clair dans le mail', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie.martin@example.test',
            'roles' => [SpatieRole::findByName(Role::Resident->value, 'web')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'marie.martin@example.test')->firstOrFail();

    Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) use ($user): bool {
        $rendered = $mail->render();

        return ! str_contains($rendered, $user->password)
            && ! str_contains(strtolower($rendered), 'mot de passe provisoire');
    });

    // Le secret posé à la création est aléatoire et inconnu de l'admin.
    expect(Hash::check('password', $user->password))->toBeFalse();
});

it('permet de définir son mot de passe avec le lien d\'accueil, puis de se connecter', function () {
    $user = User::factory()->resident()->create(['email' => 'nouveau@example.test']);
    app(WelcomeInvitationService::class)->send($user);
    $token = tokenFromUrl(welcomeUrlSentTo('nouveau@example.test'));

    $this->postJson('/reset-password/welcome', [
        'token' => $token,
        'email' => 'nouveau@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ])->assertOk();

    expect(Hash::check('Mon-mot-de-passe-42', $user->fresh()->password))->toBeTrue();

    auth()->logout();
    $this->postJson('/login', ['email' => 'nouveau@example.test', 'password' => 'Mon-mot-de-passe-42'])
        ->assertOk();
});

it('reste valide 3 jours (là où un jeton de reset expire en 1 h)', function () {
    $user = User::factory()->resident()->create(['email' => 'patient@example.test']);
    app(WelcomeInvitationService::class)->send($user);
    $token = tokenFromUrl(welcomeUrlSentTo('patient@example.test'));

    $this->travel(2)->days();

    $this->postJson('/reset-password/welcome', [
        'token' => $token,
        'email' => 'patient@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ])->assertOk();

    expect(Hash::check('Mon-mot-de-passe-42', $user->fresh()->password))->toBeTrue();
});

it('refuse proprement un lien d\'accueil expiré (422, mot de passe inchangé)', function () {
    $user = User::factory()->resident()->create(['email' => 'tardif@example.test']);
    app(WelcomeInvitationService::class)->send($user);
    $token = tokenFromUrl(welcomeUrlSentTo('tardif@example.test'));
    $before = $user->password;

    $this->travel(4)->days();

    $this->postJson('/reset-password/welcome', [
        'token' => $token,
        'email' => 'tardif@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->fresh()->password)->toBe($before);
});

it('cloisonne les deux flux : un jeton d\'accueil n\'ouvre pas /reset-password et réciproquement', function () {
    $user = User::factory()->resident()->create(['email' => 'cloison@example.test']);
    app(WelcomeInvitationService::class)->send($user);
    $welcomeToken = tokenFromUrl(welcomeUrlSentTo('cloison@example.test'));
    $resetToken = Password::broker()->createToken($user);
    $before = $user->password;

    $this->postJson('/reset-password', [
        'token' => $welcomeToken,
        'email' => 'cloison@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ])->assertUnprocessable();

    $this->postJson('/reset-password/welcome', [
        'token' => $resetToken,
        'email' => 'cloison@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ])->assertUnprocessable();

    expect($user->fresh()->password)->toBe($before);
});

it('renvoie l\'email d\'accueil depuis la fiche admin et invalide le lien précédent', function () {
    actingAs(User::factory()->admin()->create());
    $member = User::factory()->resident()->create(['email' => 'renvoi@example.test']);

    app(WelcomeInvitationService::class)->send($member);
    $firstToken = tokenFromUrl(welcomeUrlSentTo('renvoi@example.test'));

    // Le dépôt throttle à 60 s : sans cela le renvoi serait refusé.
    $this->travel(61)->seconds();

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->callAction('resendWelcome')
        ->assertHasNoActionErrors();

    Mail::assertQueued(WelcomeMail::class, 2);

    // Un seul jeton vivant à la fois : le premier ne vaut plus rien.
    expect(Password::broker(WelcomeInvitationService::BROKER)->tokenExists($member, $firstToken))->toBeFalse();
});

it('n\'envoie aucun email d\'accueil à un compte anonymisé', function () {
    $user = User::factory()->resident()->create(['anonymized_at' => now()]);

    expect(app(WelcomeInvitationService::class)->send($user))->toBeFalse();

    Mail::assertNothingQueued();
});

it('refuse un renvoi dans la minute, pour ne pas périmer le lien qui vient de partir', function () {
    $user = User::factory()->resident()->create(['email' => 'pressee@example.test']);

    expect(app(WelcomeInvitationService::class)->send($user))->toBeTrue()
        ->and(app(WelcomeInvitationService::class)->send($user))->toBeFalse();

    Mail::assertQueued(WelcomeMail::class, 1);

    // Le premier lien reste valide : rien n'a été régénéré.
    $token = tokenFromUrl(welcomeUrlSentTo('pressee@example.test'));
    expect(Password::broker(WelcomeInvitationService::BROKER)->tokenExists($user, $token))->toBeTrue();
});

it('ne consomme un lien d\'accueil qu\'une seule fois', function () {
    $user = User::factory()->resident()->create(['email' => 'rejeu@example.test']);
    app(WelcomeInvitationService::class)->send($user);
    $token = tokenFromUrl(welcomeUrlSentTo('rejeu@example.test'));

    $payload = fn (string $password): array => [
        'token' => $token,
        'email' => 'rejeu@example.test',
        'password' => $password,
        'password_confirmation' => $password,
    ];

    $this->postJson('/reset-password/welcome', $payload('Premier-mot-de-passe-42'))->assertOk();

    // Rejeu du même lien (mail transféré, historique du navigateur…) : refusé,
    // et surtout sans écraser le mot de passe que le membre vient de choisir.
    $this->postJson('/reset-password/welcome', $payload('Second-mot-de-passe-42'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(Hash::check('Premier-mot-de-passe-42', $user->fresh()->password))->toBeTrue();
});

it('limite les tentatives sur le point d\'entrée d\'accueil (429)', function () {
    $user = User::factory()->resident()->create(['email' => 'brute@example.test']);
    app(WelcomeInvitationService::class)->send($user);

    $attempt = fn () => $this->postJson('/reset-password/welcome', [
        'token' => 'jeton-devine',
        'email' => 'brute@example.test',
        'password' => 'Mon-mot-de-passe-42',
        'password_confirmation' => 'Mon-mot-de-passe-42',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $attempt()->assertUnprocessable();
    }

    $attempt()->assertStatus(429);
});

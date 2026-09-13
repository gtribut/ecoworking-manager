<?php

declare(strict_types=1);

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * PRD §3.2 « Mot de passe oublié » (recette R-03) : lien de réinitialisation
 * envoyé par email vers la SPA portail, réponse anti-énumération, reset effectif.
 */

/** URL du lien de reset notifié à l'utilisateur (Notification::fake). */
function resetPasswordUrlFor(User $user): string
{
    $url = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$url): bool {
        $url = (string) call_user_func(ResetPassword::$createUrlCallback, $user, $notification->token);

        return true;
    });

    return (string) $url;
}

it('envoie un lien de réinitialisation pointant vers la page /reset-password de la SPA portail', function () {
    Notification::fake();
    config(['domains.portal' => 'portail.ecoworking.test', 'app.url' => 'https://admin.ecoworking.test']);
    $user = User::factory()->resident()->create(['email' => 'membre@example.test']);

    $this->postJson('/forgot-password', ['email' => 'membre@example.test'])
        ->assertOk()
        ->assertJson(['message' => trans('passwords.sent')]);

    $url = resetPasswordUrlFor($user);

    expect($url)->toStartWith('https://portail.ecoworking.test/reset-password/')
        ->and($url)->toContain('?email='.urlencode('membre@example.test'));
});

it('répond exactement pareil pour un email inconnu (anti-énumération) sans rien envoyer', function () {
    Notification::fake();

    $this->postJson('/forgot-password', ['email' => 'inconnu@example.test'])
        ->assertOk()
        ->assertJson(['message' => trans('passwords.sent')]);

    Notification::assertNothingSent();
});

it('ne révèle pas non plus le throttle du broker (2e demande rapprochée = même 200)', function () {
    Notification::fake();
    User::factory()->resident()->create(['email' => 'membre@example.test']);

    $this->postJson('/forgot-password', ['email' => 'membre@example.test'])->assertOk();
    $this->postJson('/forgot-password', ['email' => 'membre@example.test'])
        ->assertOk()
        ->assertJson(['message' => trans('passwords.sent')]);
});

it('réinitialise le mot de passe avec un jeton valide et invalide les magic links en cours', function () {
    $user = User::factory()->resident()->create(['email' => 'membre@example.test']);
    MagicLinkToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'abc'),
        'expires_at' => now()->addMinutes(10),
    ]);
    $token = Password::broker()->createToken($user);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'membre@example.test',
        'password' => 'Nouveau-mot-de-passe-42',
        'password_confirmation' => 'Nouveau-mot-de-passe-42',
    ])->assertOk();

    expect(Hash::check('Nouveau-mot-de-passe-42', $user->fresh()->password))->toBeTrue()
        ->and(MagicLinkToken::where('user_id', $user->id)->exists())->toBeFalse();
});

it('refuse un jeton invalide (422) sans changer le mot de passe', function () {
    $user = User::factory()->resident()->create(['email' => 'membre@example.test']);
    $before = $user->password;

    $this->postJson('/reset-password', [
        'token' => 'jeton-bidon',
        'email' => 'membre@example.test',
        'password' => 'Nouveau-mot-de-passe-42',
        'password_confirmation' => 'Nouveau-mot-de-passe-42',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->fresh()->password)->toBe($before);
});

<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/** C2.2 — Login admin via Google OAuth (Socialite mocké). Politique : ADR-0009. */
beforeEach(function () {
    config(['services.google.hosted_domain' => 'ecoworking.fr']);
});

/** Simule le compte Google renvoyé par Socialite au callback. */
function fakeGoogleUser(string $email, bool $verified = true, ?string $hd = 'ecoworking.fr'): void
{
    $user = new SocialiteUser;
    $user->email = $email;
    $user->user = ['email_verified' => $verified, 'hd' => $hd];

    Socialite::shouldReceive('driver->user')->andReturn($user);
}

it('redirige vers Google au démarrage du flow', function () {
    $this->get(route('auth.google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

it('connecte un admin existant du domaine autorisé', function () {
    $admin = User::factory()->admin()->create(['email' => 'boss@ecoworking.fr']);
    fakeGoogleUser('boss@ecoworking.fr');

    $this->get(route('auth.google.callback'))->assertRedirect();

    $this->assertAuthenticatedAs($admin);
});

it('refuse un compte du domaine SANS rôle admin (pas d\'escalade)', function () {
    User::factory()->resident()->create(['email' => 'membre@ecoworking.fr']);
    fakeGoogleUser('membre@ecoworking.fr');

    $this->get(route('auth.google.callback'));

    $this->assertGuest();
});

it('refuse un email inconnu (pas d\'auto-provisioning)', function () {
    fakeGoogleUser('inconnu@ecoworking.fr');

    $this->get(route('auth.google.callback'));

    $this->assertGuest();
});

it('refuse un email Google non vérifié', function () {
    User::factory()->admin()->create(['email' => 'boss@ecoworking.fr']);
    fakeGoogleUser('boss@ecoworking.fr', verified: false);

    $this->get(route('auth.google.callback'));

    $this->assertGuest();
});

it('refuse un email hors du domaine autorisé, même si un admin porte cet email', function () {
    User::factory()->admin()->create(['email' => 'boss@gmail.com']);
    fakeGoogleUser('boss@gmail.com', hd: null);

    $this->get(route('auth.google.callback'));

    $this->assertGuest();
});

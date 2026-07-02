<?php

declare(strict_types=1);

use App\Models\User;

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
    postJson('/register', [
        'name' => 'X', 'email' => 'x@ecoworking.fr',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();
});

it('désactive la mise à jour de profil Fortify (flux dédiés : /api/profile, PRD §3.4.5)', function () {
    $user = User::factory()->create();

    // L'action scaffoldée écrivait une colonne `name` inexistante et aurait
    // permis un changement d'email libre, exclu du MVP — feature désactivée.
    $this->actingAs($user)
        ->putJson('/user/profile-information', [
            'name' => 'Nouveau Nom', 'email' => 'nouvel-email@ecoworking.fr',
        ])->assertNotFound();
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

it('déconnecte et détruit la session (POST /logout)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/logout')->assertSuccessful();

    $this->assertGuest();
});

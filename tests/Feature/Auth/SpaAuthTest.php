<?php

declare(strict_types=1);

use App\Models\User;

/** C2.3 — Auth portail Sanctum mode SPA (ADR-0003). */
it('expose la route CSRF cookie de Sanctum', function () {
    $this->get('/sanctum/csrf-cookie')->assertNoContent();
});

it('protège /api/user : 401 si non authentifié (réponse JSON)', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('renvoie l\'utilisateur courant avec rôles et permissions une fois authentifié', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('roles', ['resident'])
        ->assertJsonStructure(['id', 'first_name', 'email', 'two_factor_enabled', 'roles', 'permissions']);
});

it('ne fuite jamais de données sensibles dans /api/user', function () {
    $user = User::factory()->resident()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    expect($response->json())
        ->not->toHaveKeys(['password', 'calendar_token', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/**
 * Review sécurité M1 — le groupe `api` de Laravel 11+ n'embarque AUCUN
 * throttle par défaut : il doit être activé explicitement (`throttleApi()`).
 */
it('applique le middleware throttle:api sur toutes les routes /api/*', function () {
    // Le groupe `api` doit embarquer le throttle…
    expect(app('router')->getMiddlewareGroups()['api'])->toContain('throttle:api');

    // … et chaque route /api/* doit passer par ce groupe (ou porter le
    // throttle en propre) une fois les groupes résolus.
    $apiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'));

    expect($apiRoutes)->not->toBeEmpty();

    foreach ($apiRoutes as $route) {
        $resolved = app('router')->resolveMiddleware($route->gatherMiddleware());

        $throttled = collect($resolved)->contains(
            fn (string $middleware): bool => str_starts_with($middleware, ThrottleRequests::class),
        );

        expect($throttled)->toBeTrue("Route sans throttle : {$route->uri()}");
    }
});

it('renvoie 429 au-delà du plafond de requêtes', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user)->getJson('/api/user')->assertOk();
    }

    $this->actingAs($user)->getJson('/api/user')->assertStatus(429);
});
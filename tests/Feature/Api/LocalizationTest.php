<?php

declare(strict_types=1);

use App\Models\Resource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Localisation FR des erreurs de validation (APP_LOCALE=fr) : les 422 des
 * Form Requests de l'API portail doivent partir en français (traductions
 * laravel-lang), pas dans le fallback anglais de Laravel.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('renvoie les erreurs de validation d\'un Form Request en français', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();

    $response = $this->actingAs($user)->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'starts_at' => now()->subDay()->toIso8601String(),
        'ends_at' => now()->subDay()->addHour()->toIso8601String(),
    ]);

    $response->assertUnprocessable();

    // `after:now` → « …doit être une date postérieure à… » (lang/fr/validation.php).
    expect($response->json('errors.starts_at.0'))
        ->toContain('postérieure');
});

it('renvoie les erreurs « champ requis » en français', function () {
    $user = User::factory()->resident()->create();

    $response = $this->actingAs($user)->postJson('/api/bookings', []);

    $response->assertUnprocessable();

    expect($response->json('errors.resource_id.0'))
        ->toContain('obligatoire');
});

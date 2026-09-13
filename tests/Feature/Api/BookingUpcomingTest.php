<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Dashboard PRD §3.3.2 « Mes prochaines réservations » (recette R-05) :
 * `GET /api/bookings?upcoming=1` = résas confirmées non terminées, chronologiques,
 * auto-scopées au membre.
 *
 * Le nom de la ressource (`resource_name`) doit être présent sur chaque ligne
 * (recette R-07) : régression couverte ci-dessous.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions (view-own-bookings)
});

it('liste uniquement les réservations à venir, confirmées, en ordre chronologique', function () {
    $user = User::factory()->resident()->create();
    $other = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();

    $later = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDays(5)->setTime(10, 0), 'ends_at' => now()->addDays(5)->setTime(11, 0)]);
    $soon = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(10, 0)]);
    Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->subDays(2)->setTime(9, 0), 'ends_at' => now()->subDays(2)->setTime(10, 0)]);
    Booking::factory()->cancelled()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(10, 0)]);
    Booking::factory()->create(['user_id' => $other->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDays(3)->setTime(9, 0), 'ends_at' => now()->addDays(3)->setTime(10, 0)]);

    $response = $this->actingAs($user)->getJson('/api/bookings?upcoming=1&per_page=3')->assertOk();

    expect($response->json('data.*.id'))->toBe([$soon->id, $later->id])
        ->and($response->json('meta.per_page'))->toBe(3);
});

it('borne per_page à 50 et garde l’historique décroissant sans le filtre', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $past = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->subDays(2)->setTime(9, 0), 'ends_at' => now()->subDays(2)->setTime(10, 0)]);
    $future = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(10, 0)]);

    $response = $this->actingAs($user)->getJson('/api/bookings?per_page=500')->assertOk();

    expect($response->json('data.*.id'))->toBe([$future->id, $past->id])
        ->and($response->json('meta.per_page'))->toBe(50);
});

it('expose le nom de la ressource sur chaque réservation (R-07)', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create(['name' => 'Salle de réunion 2']);

    Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(10, 0)]);

    $response = $this->actingAs($user)->getJson('/api/bookings?upcoming=1')->assertOk();

    expect($response->json('data.0.resource_name'))->toBe('Salle de réunion 2');
});

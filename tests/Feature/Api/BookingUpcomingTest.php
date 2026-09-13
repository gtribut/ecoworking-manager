<?php

declare(strict_types=1);

use App\Enums\TicketType;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Dashboard PRD §3.3.2 « Mes prochaines réservations » (recette R-05) :
 * `GET /api/bookings?upcoming=1` = résas confirmées non terminées, chronologiques,
 * auto-scopées au membre.
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

it('liste l’historique avec ?past=1 : résas terminées, plus récentes d’abord', function () {
    // PRD §3.5.7 : onglet « Historique » de la page Réservations. Comparaison
    // côté SQL (piège timezone), pagination conservée.
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();

    $older = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->subDays(10)->setTime(9, 0), 'ends_at' => now()->subDays(10)->setTime(10, 0)]);
    $recent = Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->subDays(2)->setTime(9, 0), 'ends_at' => now()->subDays(2)->setTime(10, 0)]);
    Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(10, 0)]);

    $response = $this->actingAs($user)->getJson('/api/bookings?past=1')->assertOk();

    expect($response->json('data.*.id'))->toBe([$recent->id, $older->id])
        ->and($response->json('data.0.cancellable'))->toBeFalse();
});

it('n’expose l’historique d’aucun autre membre (isolation A/B)', function () {
    $memberA = User::factory()->resident()->create();
    $memberB = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $theirs = Booking::factory()->create(['user_id' => $memberB->id, 'resource_id' => $room->id,
        'starts_at' => now()->subDays(3)->setTime(9, 0), 'ends_at' => now()->subDays(3)->setTime(10, 0)]);

    $response = $this->actingAs($memberA)->getJson('/api/bookings?past=1')->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('data.*.id'))->not->toContain($theirs->id);
});

it('expose le ticket consommé par réservation (traçabilité external)', function () {
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $ticket = Ticket::factory()->for($user)->used()->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'ticket_id' => $ticket->id,
        'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(13, 0)]);

    $this->actingAs($user)->getJson('/api/bookings?upcoming=1')
        ->assertOk()
        ->assertJsonPath('data.0.is_paid', true)
        ->assertJsonPath('data.0.ticket.id', $ticket->id)
        ->assertJsonPath('data.0.ticket.type', TicketType::MeetingRoomHalfDay->value);
});

it('expose le nom de la salle de chaque réservation (R-07)', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create(['name' => 'Salle Rhône']);
    Booking::factory()->create(['user_id' => $user->id, 'resource_id' => $room->id,
        'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(10, 0)]);

    $this->actingAs($user)->getJson('/api/bookings?upcoming=1')
        ->assertOk()
        ->assertJsonPath('data.0.resource_name', 'Salle Rhône');
});

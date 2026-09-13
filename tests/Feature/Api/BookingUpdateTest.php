<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

/**
 * PRD §3.5.5 — modification d'une réservation depuis le portail
 * (`PATCH /api/bookings/{booking}`) : mêmes règles que la création, isolation
 * stricte et gestion du ticket external.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

function updateNextWorkingDay(int $offsetDays = 1): CarbonImmutable
{
    $day = CarbonImmutable::today()->addDays($offsetDays);
    while (! FrenchHolidays::isWorkingDay($day)) {
        $day = $day->addDay();
    }

    return $day;
}

it('permet à un membre de modifier sa propre réservation', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(11, 0),
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(14, 0)->toIso8601String(),
        'ends_at' => $day->setTime(16, 0)->toIso8601String(),
        'title' => 'Atelier produit',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Atelier produit')
        ->assertJsonPath('data.cancellable', true);

    $booking->refresh();
    expect($booking->title)->toBe('Atelier produit')
        ->and($booking->starts_at->format('H:i'))->toBe('14:00')
        ->and($booking->ends_at->format('H:i'))->toBe('16:00');
});

it('refuse la modification de la réservation d\'un autre membre (403)', function () {
    $owner = User::factory()->resident()->create();
    $intruder = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(11, 0),
    ]);

    $this->actingAs($intruder)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(14, 0)->toIso8601String(),
        'ends_at' => $day->setTime(15, 0)->toIso8601String(),
    ])->assertForbidden();

    expect($booking->fresh()->starts_at->format('H:i'))->toBe('10:00');
});

it('refuse la modification d\'une réservation déjà commencée (403, comparaison SQL)', function () {
    // Piège timezone : la ligne fraîche relue en PHP paraît ~2 h dans le futur.
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->addMinutes(30),
    ]);
    $day = updateNextWorkingDay();

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(14, 0)->toIso8601String(),
        'ends_at' => $day->setTime(15, 0)->toIso8601String(),
    ])->assertForbidden();
});

it('renvoie 409 si le nouveau créneau chevauche une autre réservation', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(9, 0),
        'ends_at' => $day->setTime(10, 0),
    ]);
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(14, 0),
        'ends_at' => $day->setTime(16, 0),
    ]);

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(15, 0)->toIso8601String(),
        'ends_at' => $day->setTime(17, 0)->toIso8601String(),
    ])->assertStatus(409);

    expect($booking->fresh()->starts_at->format('H:i'))->toBe('09:00');
});

it('accepte de ne changer que le libellé sans se heurter à sa propre réservation', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(9, 0),
        'ends_at' => $day->setTime(10, 0),
    ]);

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(9, 0)->toIso8601String(),
        'ends_at' => $day->setTime(10, 0)->toIso8601String(),
        'title' => 'Comité de pilotage',
    ])->assertOk();

    expect($booking->fresh()->title)->toBe('Comité de pilotage');
});

it('refuse de déplacer une réservation vers la salle événementielle (403)', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $eventRoom = Resource::factory()->eventRoom()->create(['requires_admin' => true]);
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(11, 0),
    ]);

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $eventRoom->id,
        'starts_at' => $day->setTime(14, 0)->toIso8601String(),
        'ends_at' => $day->setTime(15, 0)->toIso8601String(),
    ])->assertForbidden();

    expect($booking->fresh()->resource_id)->toBe($room->id);
});

it('restitue puis re-consomme un ticket quand l\'external change de demi-journée', function () {
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $ticket = Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);

    $this->actingAs($user)->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'date' => $day->toDateString(),
        'period' => 'morning',
    ])->assertCreated();

    $booking = Booking::where('user_id', $user->id)->firstOrFail();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Used);

    $this->actingAs($user)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'date' => $day->toDateString(),
        'period' => 'afternoon',
    ])->assertOk()
        ->assertJsonPath('data.is_paid', true);

    $booking->refresh();
    expect($booking->starts_at->format('H:i'))->toBe('14:00')
        ->and($booking->ends_at->format('H:i'))->toBe('18:00')
        // Une demi-journée réservée = un seul ticket consommé (solde inchangé).
        ->and(Ticket::where('user_id', $user->id)->where('status', TicketStatus::Available->value)->count())->toBe(1)
        ->and(Ticket::whereKey($booking->ticket_id)->value('status'))->toBe(TicketStatus::Used);
});

it('refuse 422 le changement de demi-journée d\'un external sans ticket disponible', function () {
    // Résa créée par l'admin sans ticket : le déplacement en exige un.
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(9, 0),
        'ends_at' => $day->setTime(13, 0),
        'ticket_id' => null,
    ]);

    $this->actingAs($user)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'date' => $day->toDateString(),
        'period' => 'afternoon',
    ])->assertStatus(422);

    expect($booking->fresh()->starts_at->format('H:i'))->toBe('09:00');
});

it('refuse une demi-journée external un jour non ouvré (422)', function () {
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $saturday = $day->next(CarbonInterface::SATURDAY);
    Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(9, 0),
        'ends_at' => $day->setTime(13, 0),
    ]);

    $this->actingAs($user)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'date' => $saturday->toDateString(),
        'period' => 'morning',
    ])->assertStatus(422);
});

it('trace la modification dans le journal d\'audit', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = updateNextWorkingDay();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(11, 0),
    ]);

    $this->actingAs($owner)->patchJson("/api/bookings/{$booking->id}", [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(15, 0)->toIso8601String(),
        'ends_at' => $day->setTime(16, 0)->toIso8601String(),
    ])->assertOk();

    expect(
        Activity::query()
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->id)
            ->where('event', 'updated')
            ->exists()
    )->toBeTrue();
});

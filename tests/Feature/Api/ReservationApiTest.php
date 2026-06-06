<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;

/** C4.4 / C4.5 — API portail réservation salle, tickets, bureaux & présence. */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

function apiNextWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

// --- Auth ----------------------------------------------------------------

it('protège les endpoints résa : 401 si non authentifié', function () {
    $this->getJson('/api/rooms')->assertUnauthorized();
    $this->getJson('/api/bookings')->assertUnauthorized();
    $this->getJson('/api/tickets')->assertUnauthorized();
});

// --- C4.4 Réservation salle (resident) -----------------------------------

it('permet à un résident de réserver une salle (créneau libre)', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = apiNextWorkingDay();

    $this->actingAs($user)->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0)->toIso8601String(),
        'ends_at' => $day->setTime(11, 0)->toIso8601String(),
        'title' => 'Point équipe',
    ])->assertCreated()
        ->assertJsonPath('data.status', BookingStatus::Confirmed->value);

    expect(Booking::where('user_id', $user->id)->count())->toBe(1);
});

it('renvoie 409 si le créneau chevauche une réservation existante', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = apiNextWorkingDay();
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(12, 0),
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs(User::factory()->resident()->create())->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'starts_at' => $day->setTime(11, 0)->toIso8601String(),
        'ends_at' => $day->setTime(13, 0)->toIso8601String(),
    ])->assertStatus(409);
});

// --- C4.4 Réservation salle (external via ticket) ------------------------

it('permet à un external de réserver une demi-journée en consommant un ticket', function () {
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $ticket = Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    $day = apiNextWorkingDay();

    $this->actingAs($user)->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'date' => $day->toDateString(),
        'period' => 'morning',
    ])->assertCreated()
        ->assertJsonPath('data.is_paid', true);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Used);
});

it('refuse une résa external sans ticket disponible (422)', function () {
    $user = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = apiNextWorkingDay();

    $this->actingAs($user)->postJson('/api/bookings', [
        'resource_id' => $room->id,
        'date' => $day->toDateString(),
        'period' => 'morning',
    ])->assertStatus(422);
});

// --- C4.4 Annulation & isolation -----------------------------------------

it('annule sa propre réservation, mais pas celle d\'un autre membre (403)', function () {
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => apiNextWorkingDay()->setTime(14, 0),
        'ends_at' => apiNextWorkingDay()->setTime(15, 0),
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs(User::factory()->resident()->create())
        ->deleteJson("/api/bookings/{$booking->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->deleteJson("/api/bookings/{$booking->id}")
        ->assertOk();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

// --- C4.5 Tickets --------------------------------------------------------

it('expose les soldes de tickets du membre', function () {
    $user = User::factory()->external()->create();
    Ticket::factory()->count(2)->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);

    $this->actingAs($user)->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonPath('balances.desk_half_day', 2)
        ->assertJsonPath('balances.meeting_room_half_day', 1);
});

// --- C4.5 Bureaux nomades ------------------------------------------------

it('réserve un bureau external (occupation + ticket) et le retire des dispos', function () {
    $user = User::factory()->external()->create();
    $desk = Resource::factory()->desk()->create();
    Ticket::factory()->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    $day = apiNextWorkingDay();

    $this->actingAs($user)->postJson('/api/desk-occupations', [
        'desk_id' => $desk->id,
        'date' => $day->toDateString(),
        'period' => 'morning',
    ])->assertCreated();

    $this->actingAs($user)->getJson("/api/desks/availability?date={$day->toDateString()}&period=morning")
        ->assertOk()
        ->assertJsonPath('count', 0);
});

// --- C4.5 Présence résident ----------------------------------------------

it('permet à un résident de déclarer une absence sur son bureau attitré', function () {
    $desk = Resource::factory()->assignedResident()->create();
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);
    $day = apiNextWorkingDay();

    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => $day->toDateString(),
        'period' => 'full_day',
    ])->assertCreated();

    expect(DeskAbsence::where('user_id', $user->id)->count())->toBe(1);

    $this->actingAs($user)->getJson("/api/presence?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk()
        ->assertJsonPath('present_days', []);
});

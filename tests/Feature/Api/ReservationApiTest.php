<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\DeskOccupationStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;

/** C4.4 / C4.5 — API portail réservation salle, tickets, bureaux & présence. */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

// Filet : si un test fige l'horloge (délai d'annulation bureau) et échoue
// avant de la relâcher, les tests suivants ne doivent jamais hériter d'un
// « now() » figé.
afterEach(function () {
    Carbon::setTestNow();
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

// --- C4.4 Disponibilité d'une salle ---------------------------------------

it('renvoie 404 sur la dispo d\'une ressource qui n\'est pas une salle de réunion', function () {
    $desk = Resource::factory()->desk()->create();
    $day = apiNextWorkingDay();

    // Moindre exposition (review sécu I1) : un bureau ne doit pas répondre
    // avec ses créneaux via l'endpoint salles.
    $this->actingAs(User::factory()->resident()->create())
        ->getJson("/api/rooms/{$desk->id}/availability?date={$day->toDateString()}")
        ->assertNotFound();
});

it('compte une résa à cheval sur minuit dans les créneaux occupés du lendemain', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = apiNextWorkingDay()->addDay();
    $eve = $day->subDay();
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => $eve->setTime(23, 0),
        'ends_at' => $day->setTime(1, 0), // franchit minuit
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs(User::factory()->resident()->create())
        ->getJson("/api/rooms/{$room->id}/availability?date={$day->toDateString()}")
        ->assertOk()
        ->assertJsonCount(1, 'busy');
});

it('n\'inclut pas dans la dispo du jour une résa se terminant exactement à minuit', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = apiNextWorkingDay()->addDay();
    $eve = $day->subDay();
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => $eve->setTime(22, 0),
        'ends_at' => $day->startOfDay(), // borne semi-ouverte : pas le lendemain
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs(User::factory()->resident()->create())
        ->getJson("/api/rooms/{$room->id}/availability?date={$day->toDateString()}")
        ->assertOk()
        ->assertJsonCount(0, 'busy');
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

it('ne liste que ses propres réservations (isolation A/B, review DB M2)', function () {
    $memberA = User::factory()->resident()->create();
    $memberB = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $bookingA = Booking::factory()->create(['user_id' => $memberA->id, 'resource_id' => $room->id]);
    $bookingB = Booking::factory()->create(['user_id' => $memberB->id, 'resource_id' => $room->id]);

    $ids = collect($this->actingAs($memberA)->getJson('/api/bookings')->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($bookingA->id)
        ->not->toContain($bookingB->id);
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

it('ne liste ni ne compte les tickets d\'un autre membre (isolation A/B)', function () {
    $memberA = User::factory()->external()->create();
    $memberB = User::factory()->external()->create();
    Ticket::factory()->for($memberB)->create(['type' => TicketType::DeskHalfDay->value]);

    $this->actingAs($memberA)->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonPath('balances.desk_half_day', 0)
        ->assertJsonPath('tickets', []);
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

it('refuse une réservation de bureau external un jour non ouvré (ticket intact)', function () {
    // Décision 2026-07-03 (review finding #17) : les tickets external suivent
    // les jours ouvrés, bureaux comme salles.
    $user = User::factory()->external()->create();
    $desk = Resource::factory()->desk()->create();
    $ticket = Ticket::factory()->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    $saturday = apiNextWorkingDay()->next(CarbonInterface::SATURDAY);

    $this->actingAs($user)->postJson('/api/desk-occupations', [
        'desk_id' => $desk->id,
        'date' => $saturday->toDateString(),
        'period' => 'morning',
    ])->assertUnprocessable();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Available)
        ->and(DeskOccupation::count())->toBe(0);
});

it('annule sa propre occupation de bureau, mais pas celle d\'un autre (403)', function () {
    $owner = User::factory()->external()->create();
    $intruder = User::factory()->external()->create();
    $ticket = Ticket::factory()->for($owner)->used()->create(['type' => TicketType::DeskHalfDay->value]);
    // Date future déterministe (au-delà du délai d'annulation, jamais « aujourd'hui »
    // aléatoire) : la Policy vérifie désormais le délai (cf. tests dédiés plus bas).
    $occupation = DeskOccupation::factory()->create([
        'user_id' => $owner->id,
        'ticket_id' => $ticket->id,
        'date' => apiNextWorkingDay()->toDateString(),
    ]);

    $this->actingAs($intruder)
        ->deleteJson("/api/desk-occupations/{$occupation->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->deleteJson("/api/desk-occupations/{$occupation->id}")
        ->assertOk();

    expect($occupation->fresh()->status)->toBe(DeskOccupationStatus::Cancelled)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Available); // restitué
});

// --- C4.5 Bureaux nomades — liste « Mes bureaux réservés » (lot E) -------

it('liste les occupations à venir du membre, pas celles d\'un autre (isolation A/B)', function () {
    $memberA = User::factory()->external()->create();
    $memberB = User::factory()->external()->create();
    $day = apiNextWorkingDay();
    $occupationA = DeskOccupation::factory()->create(['user_id' => $memberA->id, 'date' => $day->toDateString()]);
    DeskOccupation::factory()->create(['user_id' => $memberB->id, 'date' => $day->toDateString()]);

    $response = $this->actingAs($memberA)->getJson('/api/desk-occupations')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($occupationA->id)->toHaveCount(1);
});

it('n\'expose pas les occupations annulées dans « à venir », mais les garde dans l\'historique', function () {
    $user = User::factory()->external()->create();
    $future = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'date' => apiNextWorkingDay()->toDateString(),
    ]);
    $cancelled = DeskOccupation::factory()->cancelled()->create([
        'user_id' => $user->id,
        'date' => apiNextWorkingDay()->toDateString(),
    ]);
    $past = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::yesterday()->toDateString(),
    ]);

    $upcomingIds = collect(
        $this->actingAs($user)->getJson('/api/desk-occupations')->assertOk()->json('data')
    )->pluck('id');
    $pastIds = collect(
        $this->actingAs($user)->getJson('/api/desk-occupations?past=1')->assertOk()->json('data')
    )->pluck('id');

    expect($upcomingIds)->toContain($future->id)
        ->not->toContain($cancelled->id)
        ->not->toContain($past->id)
        ->and($pastIds)->toContain($past->id)
        ->not->toContain($future->id);
});

it('refuse la liste des bureaux réservés à un résident (pas de create-paid-booking)', function () {
    $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/desk-occupations')
        ->assertForbidden();
});

it('marque une occupation d\'aujourd\'hui après-midi annulable à 10h, plus à 15h', function () {
    $user = User::factory()->external()->create();
    $occupation = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'period' => 'afternoon',
    ]);

    Carbon::setTestNow(Carbon::today()->setTime(10, 0));
    $this->actingAs($user)
        ->deleteJson("/api/desk-occupations/{$occupation->id}")
        ->assertOk();

    Carbon::setTestNow();
});

it('refuse d\'annuler une occupation d\'aujourd\'hui après-midi à 15h (délai dépassé)', function () {
    $user = User::factory()->external()->create();
    $occupation = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::today()->toDateString(),
        'period' => 'afternoon',
    ]);

    Carbon::setTestNow(Carbon::today()->setTime(15, 0));
    $this->actingAs($user)
        ->deleteJson("/api/desk-occupations/{$occupation->id}")
        ->assertForbidden();

    Carbon::setTestNow();

    expect($occupation->fresh()->status)->toBe(DeskOccupationStatus::Present);
});

it('refuse d\'annuler une occupation passée (403)', function () {
    $user = User::factory()->external()->create();
    $occupation = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::yesterday()->toDateString(),
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/desk-occupations/{$occupation->id}")
        ->assertForbidden();
});

// --- C4.5 Tickets — détail par ticket (lot E) -----------------------------

it('expose le détail par ticket : statut, crédit, utilisation associée', function () {
    $user = User::factory()->external()->create();
    $desk = Resource::factory()->desk()->create(['name' => 'Bureau Rhône']);
    $day = apiNextWorkingDay();
    $usedTicket = Ticket::factory()->for($user)->used()->create(['type' => TicketType::DeskHalfDay->value]);
    $occupation = DeskOccupation::factory()->create([
        'user_id' => $user->id,
        'desk_id' => $desk->id,
        'ticket_id' => $usedTicket->id,
        'date' => $day->toDateString(),
        'period' => 'morning',
    ]);
    // Réciproque de `ticket_id` : `TicketService::consume()` la renseigne à la
    // réservation réelle, on la simule ici pour isoler l'assertion sur l'API.
    $usedTicket->update(['desk_occupation_id' => $occupation->id]);
    Ticket::factory()->for($user)->credited('Geste commercial')->create(['type' => TicketType::DeskHalfDay->value]);

    $tickets = collect(
        $this->actingAs($user)->getJson('/api/tickets')->assertOk()->json('tickets')
    );

    $usedEntry = $tickets->firstWhere('id', $usedTicket->id);
    expect($usedEntry['status'])->toBe(TicketStatus::Used->value)
        ->and($usedEntry['usage']['kind'])->toBe('desk_occupation')
        ->and($usedEntry['usage']['resource_name'])->toBe('Bureau Rhône')
        ->and($usedEntry['usage']['date'])->toBe($day->toDateString())
        ->and($usedEntry['credited_at'])->not->toBeNull();

    $creditedEntry = $tickets->firstWhere('status', TicketStatus::Available->value);
    expect($creditedEntry['usage'])->toBeNull();
});

// --- C4.5 Disponibilité bureau — jours non ouvrés (lot E) -----------------

it('signale un jour férié comme non ouvré sur la dispo bureau (au lieu d\'une liste vide indistincte)', function () {
    $user = User::factory()->external()->create();
    $bastilleDay = CarbonImmutable::create((int) CarbonImmutable::today()->addYear()->year, 7, 14);

    $response = $this->actingAs($user)
        ->getJson("/api/desks/availability?date={$bastilleDay->toDateString()}&period=morning")
        ->assertOk();

    expect($response->json('available'))->toBeFalse()
        ->and($response->json('reason'))->toBe('non_working_day')
        ->and($response->json('count'))->toBe(0);
});

it('signale un week-end comme non ouvré sur la dispo bureau', function () {
    $user = User::factory()->external()->create();
    $saturday = apiNextWorkingDay()->next(CarbonInterface::SATURDAY);

    $this->actingAs($user)
        ->getJson("/api/desks/availability?date={$saturday->toDateString()}&period=morning")
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('reason', 'non_working_day');
});

it('renvoie available=true sur un jour ouvré avec des bureaux libres', function () {
    $user = User::factory()->external()->create();
    Resource::factory()->desk()->create();
    $day = apiNextWorkingDay();

    $this->actingAs($user)
        ->getJson("/api/desks/availability?date={$day->toDateString()}&period=morning")
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('reason', null);
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

it('supprime sa propre absence, mais pas celle d\'un autre membre (403)', function () {
    $owner = User::factory()->resident()->create();
    $desk = Resource::factory()->assignedResident()->create();
    MemberProfile::factory()->for($owner)->create(['desk_id' => $desk->id]);
    $absence = DeskAbsence::factory()->create(['user_id' => $owner->id, 'desk_id' => $desk->id]);

    $this->actingAs(User::factory()->resident()->create())
        ->deleteJson("/api/absences/{$absence->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->deleteJson("/api/absences/{$absence->id}")
        ->assertOk();

    expect(DeskAbsence::query()->count())->toBe(0);
});

it('refuse d\'annuler une réservation déjà commencée (403, comparaison SQL)', function () {
    // Piège timezone du repo : une ligne fraîche relue en PHP paraît ~2 h dans
    // le futur → `starts_at->isFuture()` autoriserait l'annulation d'un créneau
    // en cours. La Policy doit comparer côté SQL.
    $owner = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $booking = Booking::factory()->create([
        'user_id' => $owner->id,
        'resource_id' => $room->id,
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->addMinutes(30),
        'status' => BookingStatus::Confirmed->value,
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/bookings/{$booking->id}")
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

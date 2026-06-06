<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\BookingConflictException;
use App\Exceptions\DomainActionException;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Offer;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeskAvailabilityService;
use App\Services\PresenceService;
use App\Services\PurchaseService;
use App\Services\RoomAvailabilityService;
use App\Services\TicketService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;

/** Prochain jour ouvré à partir d'aujourd'hui (évite week-ends/fériés). */
function nextWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

// --- C7.1 BookingService (anti-double-booking) ---------------------------

it('crée une réservation de salle confirmée', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $user = User::factory()->create();
    $day = nextWorkingDay();

    $booking = app(BookingService::class)->create([
        'resource' => $room,
        'user' => $user,
        'starts_at' => $day->setTime(10, 0),
        'ends_at' => $day->setTime(11, 0),
    ]);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->resource_id)->toBe($room->id);
});

it('refuse un créneau qui en chevauche un autre (conflit)', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = nextWorkingDay();
    $svc = app(BookingService::class);

    $svc->create([
        'resource' => $room, 'user' => User::factory()->create(),
        'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(12, 0),
    ]);

    $svc->create([
        'resource' => $room, 'user' => User::factory()->create(),
        'starts_at' => $day->setTime(11, 0), 'ends_at' => $day->setTime(13, 0),
    ]);
})->throws(BookingConflictException::class);

it('autorise deux créneaux adjacents (bornes semi-ouvertes)', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = nextWorkingDay();
    $svc = app(BookingService::class);

    $svc->create([
        'resource' => $room, 'user' => User::factory()->create(),
        'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(11, 0),
    ]);
    $second = $svc->create([
        'resource' => $room, 'user' => User::factory()->create(),
        'starts_at' => $day->setTime(11, 0), 'ends_at' => $day->setTime(12, 0),
    ]);

    expect($second->status)->toBe(BookingStatus::Confirmed);
});

it('consomme le ticket à la réservation external puis le restitue à l\'annulation', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    $day = nextWorkingDay();

    $booking = app(BookingService::class)->create([
        'resource' => $room, 'user' => $user,
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(13, 0),
        'ticket' => $ticket,
    ]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Used)
        ->and($ticket->fresh()->booking_id)->toBe($booking->id);

    app(BookingService::class)->cancel($booking, 'changement de plan');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Available)
        ->and($ticket->fresh()->consumed_at)->toBeNull();
});

// --- C7.5 TicketService --------------------------------------------------

it('refuse de consommer un ticket dont le type ne correspond pas à la cible', function () {
    $user = User::factory()->create();
    $deskTicket = Ticket::factory()->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    $room = Resource::factory()->meetingRoom()->create();
    $booking = Booking::factory()->create(['resource_id' => $room->id]);

    app(TicketService::class)->consume($deskTicket, $booking);
})->throws(DomainActionException::class);

it('compte les tickets disponibles par type', function () {
    $user = User::factory()->create();
    Ticket::factory()->count(3)->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    Ticket::factory()->for($user)->used()->create(['type' => TicketType::DeskHalfDay->value]);

    expect(app(TicketService::class)->availableCount($user, TicketType::DeskHalfDay))->toBe(3);
});

// --- C7.5 PurchaseService ------------------------------------------------

it('génère N tickets disponibles avec prix figé depuis l\'offre', function () {
    $offer = Offer::factory()->pack(10)->create(['unit_price_ht' => 17.50, 'vat_rate' => 20.00]);
    $user = User::factory()->create();

    $result = app(PurchaseService::class)->createFromOffer($offer, $user, $user, $user->id);

    expect($result['tickets'])->toHaveCount(10)
        ->and($result['purchase']->unit_price_ht)->toBe('17.50')
        ->and($result['purchase']->quantity)->toBe(10)
        ->and(app(TicketService::class)->availableCount($user, TicketType::DeskHalfDay))->toBe(10);
});

// --- C7.2 RoomAvailabilityService ----------------------------------------

it('ne propose aucune demi-journée external le week-end', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $saturday = CarbonImmutable::today()->next(CarbonImmutable::SATURDAY);

    expect(app(RoomAvailabilityService::class)->externalSlotsFor($room, $saturday))->toBe([]);
});

it('propose deux demi-journées libres un jour ouvré, et masque celle déjà prise', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $day = nextWorkingDay();
    $svc = app(RoomAvailabilityService::class);

    expect($svc->externalSlotsFor($room, $day))->toHaveCount(2);

    app(BookingService::class)->create([
        'resource' => $room, 'user' => User::factory()->create(),
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(13, 0),
    ]);

    $slots = $svc->externalSlotsFor($room, $day);
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['period'])->toBe(Period::Afternoon);
});

// --- C7.4 DeskAvailabilityService ----------------------------------------

it('réserve un bureau external (occupation + ticket consommé) et le retire des dispos', function () {
    $desk = Resource::factory()->desk()->create();
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create(['type' => TicketType::DeskHalfDay->value]);
    $day = nextWorkingDay();
    $svc = app(DeskAvailabilityService::class);

    expect($svc->availableDesks($day, Period::Morning)->pluck('id'))->toContain($desk->id);

    $occupation = $svc->bookForExternal($user, $desk, $day, Period::Morning, $ticket);

    expect($occupation->status)->toBe(DeskOccupationStatus::Present)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Used)
        ->and($svc->availableDesks($day, Period::Morning)->pluck('id'))->not->toContain($desk->id);
});

it('refuse de réserver un bureau déjà occupé sur le créneau', function () {
    $desk = Resource::factory()->desk()->create();
    $day = nextWorkingDay();
    $svc = app(DeskAvailabilityService::class);

    $u1 = User::factory()->create();
    $svc->bookForExternal($u1, $desk, $day, Period::FullDay, Ticket::factory()->for($u1)->create(['type' => TicketType::DeskHalfDay->value]));

    $u2 = User::factory()->create();
    $svc->bookForExternal($u2, $desk, $day, Period::Morning, Ticket::factory()->for($u2)->create(['type' => TicketType::DeskHalfDay->value]));
})->throws(DomainActionException::class);

// --- C7.3 PresenceService ------------------------------------------------

it('considère le résident présent par défaut un jour ouvré, absent si déclaré', function () {
    $desk = Resource::factory()->assignedResident()->create();
    $user = User::factory()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);
    $day = nextWorkingDay();
    $svc = app(PresenceService::class);

    expect($svc->isPresent($user->fresh(), $day))->toBeTrue();

    DeskAbsence::factory()->for($user)->create([
        'desk_id' => $desk->id,
        'date_start' => $day->format('Y-m-d'),
        'period' => Period::FullDay->value,
    ]);

    expect($svc->isPresent($user->fresh(), $day))->toBeFalse();
});

it('applique une absence récurrente hebdomadaire à la lecture', function () {
    $desk = Resource::factory()->assignedResident()->create();
    $user = User::factory()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);
    $svc = app(PresenceService::class);

    // Premier jour ouvré, puis on cible le même jour de semaine 7 jours plus tard.
    $base = nextWorkingDay();
    $sameWeekdayLater = $base->addWeek();

    DeskAbsence::factory()->for($user)->weekly($base->dayOfWeek)->create([
        'desk_id' => $desk->id,
        'date_start' => $base->format('Y-m-d'),
        'date_end' => null,
        'period' => Period::FullDay->value,
    ]);

    expect($svc->isPresent($user->fresh(), $base))->toBeFalse()
        ->and($svc->isPresent($user->fresh(), $sameWeekdayLater))->toBeFalse();
});

it('ne déclare pas présent un membre sans bureau attitré', function () {
    $user = User::factory()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => null]);

    expect(app(PresenceService::class)->isPresent($user->fresh(), nextWorkingDay()))->toBeFalse();
});

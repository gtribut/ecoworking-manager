<?php

declare(strict_types=1);

use App\Enums\Period;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\DomainActionException;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ManualTicketConsumptionService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * C12.2 — Gardes métier de la consommation manuelle admin (PRD §4.8.1).
 * Les chemins nominaux (occupation/booking créés) sont couverts au niveau
 * UI dans TicketsResourceTest ; ici, les refus.
 */

/** Prochain jour ouvré (évite week-ends/fériés pour les créneaux external). */
function manualConsumptionNextWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

it('refuse de consommer un ticket qui n\'est plus disponible', function () {
    $desk = Resource::factory()->desk()->create();
    $ticket = Ticket::factory()->used()->create(['type' => TicketType::DeskHalfDay->value]);
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ManualTicketConsumptionService::class)->consume(
        $ticket, $desk, manualConsumptionNextWorkingDay(), Period::Morning, $admin->id,
    ))->toThrow(DomainActionException::class, "Ce ticket n'est plus disponible.");
});

it('refuse une consommation en journée complète (demi-journée uniquement)', function () {
    $desk = Resource::factory()->desk()->create();
    $ticket = Ticket::factory()->create(['type' => TicketType::DeskHalfDay->value]);
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ManualTicketConsumptionService::class)->consume(
        $ticket, $desk, manualConsumptionNextWorkingDay(), Period::FullDay, $admin->id,
    ))->toThrow(DomainActionException::class);
});

it('refuse un bureau déjà occupé sur le créneau (ticket intact)', function () {
    $desk = Resource::factory()->desk()->create();
    $admin = User::factory()->admin()->create();
    $day = manualConsumptionNextWorkingDay();

    $first = Ticket::factory()->create(['type' => TicketType::DeskHalfDay->value]);
    app(ManualTicketConsumptionService::class)->consume($first, $desk, $day, Period::Morning, $admin->id);

    $second = Ticket::factory()->create(['type' => TicketType::DeskHalfDay->value]);

    expect(fn () => app(ManualTicketConsumptionService::class)->consume(
        $second, $desk, $day, Period::Morning, $admin->id,
    ))->toThrow(DomainActionException::class, 'Ce bureau est déjà occupé sur ce créneau.');

    expect($second->refresh()->status)->toBe(TicketStatus::Available);
});

it('refuse un créneau salle un jour non ouvré (ticket intact)', function () {
    $room = Resource::factory()->meetingRoom()->create();
    $ticket = Ticket::factory()->create(['type' => TicketType::MeetingRoomHalfDay->value]);
    $admin = User::factory()->admin()->create();
    $sunday = CarbonImmutable::today()->next(CarbonInterface::SUNDAY);

    expect(fn () => app(ManualTicketConsumptionService::class)->consume(
        $ticket, $room, $sunday, Period::Morning, $admin->id,
    ))->toThrow(DomainActionException::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Available);
});

it('refuse une cible incompatible avec le type du ticket', function () {
    // Ticket bureau visant une salle : la garde « bureau nomade » refuse.
    $room = Resource::factory()->meetingRoom()->create();
    $ticket = Ticket::factory()->create(['type' => TicketType::DeskHalfDay->value]);
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ManualTicketConsumptionService::class)->consume(
        $ticket, $room, manualConsumptionNextWorkingDay(), Period::Morning, $admin->id,
    ))->toThrow(DomainActionException::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Available);
});

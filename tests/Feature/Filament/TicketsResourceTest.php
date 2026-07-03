<?php

declare(strict_types=1);

use App\Enums\DeskOccupationSource;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Booking;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.2 — Tickets côté admin (PRD §4.8.1) : liste lecture seule filtrable,
 * crédit manuel (PurchaseService::creditManual) et consommation manuelle
 * (ManualTicketConsumptionService). Rendu testé au niveau composant Livewire
 * (le 2FA obligatoire casse l'accès HTTP).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->admin()->create();
    actingAs($this->admin);
});

/** Prochain jour ouvré (évite week-ends/fériés pour les créneaux external). */
function ticketsAdminNextWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

// --- Liste & filtres -------------------------------------------------------

it('rend la liste des tickets pour un admin', function () {
    $tickets = Ticket::factory()->count(3)->create();

    Livewire::test(ListTickets::class)
        ->assertOk()
        ->assertCanSeeTableRecords($tickets);
});

it('filtre les tickets par type, statut et membre', function () {
    $member = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    $deskAvailable = Ticket::factory()->create([
        'user_id' => $member->id,
        'type' => TicketType::DeskHalfDay->value,
    ]);
    $roomUsed = Ticket::factory()->used()->create([
        'user_id' => $other->id,
        'type' => TicketType::MeetingRoomHalfDay->value,
    ]);

    Livewire::test(ListTickets::class)
        ->filterTable('type', TicketType::DeskHalfDay->value)
        ->assertCanSeeTableRecords([$deskAvailable])
        ->assertCanNotSeeTableRecords([$roomUsed])
        ->resetTableFilters()
        ->filterTable('status', TicketStatus::Used->value)
        ->assertCanSeeTableRecords([$roomUsed])
        ->assertCanNotSeeTableRecords([$deskAvailable])
        ->resetTableFilters()
        ->filterTable('user_id', $member->id)
        ->assertCanSeeTableRecords([$deskAvailable])
        ->assertCanNotSeeTableRecords([$roomUsed]);
});

it('refuse à un membre la liste des tickets (TicketPolicy::viewAny)', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListTickets::class)->assertForbidden();
});

// --- Crédit manuel ----------------------------------------------------------

it('crédite manuellement des tickets et trace credited_by / credit_reason', function () {
    $member = User::factory()->member()->create();

    Livewire::test(ListTickets::class)
        ->callAction('creditManual', [
            'user_id' => $member->id,
            'type' => TicketType::DeskHalfDay->value,
            'quantity' => 3,
            'reason' => 'Compensation panne wifi',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Tickets crédités');

    $tickets = Ticket::query()->where('user_id', $member->id)->get();

    expect($tickets)->toHaveCount(3)
        ->and($tickets->every(fn (Ticket $ticket): bool => $ticket->status === TicketStatus::Available
            && $ticket->type === TicketType::DeskHalfDay
            && $ticket->purchase_id === null
            && $ticket->credited_by === $this->admin->id
            && $ticket->credit_reason === 'Compensation panne wifi'))->toBeTrue();
});

it('valide strictement le formulaire de crédit manuel', function () {
    Livewire::test(ListTickets::class)
        ->callAction('creditManual', [
            'user_id' => null,
            'type' => null,
            'quantity' => 0,
            'reason' => '',
        ])
        ->assertHasActionErrors(['user_id', 'type', 'quantity', 'reason']);

    expect(Ticket::query()->count())->toBe(0);
});

// --- Consommation manuelle --------------------------------------------------

it('consomme manuellement un ticket bureau : occupation créée + ticket utilisé', function () {
    $member = User::factory()->member()->create();
    $desk = Resource::factory()->desk()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $member->id,
        'type' => TicketType::DeskHalfDay->value,
    ]);
    $day = ticketsAdminNextWorkingDay();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make('consume')->table($ticket), [
            'date' => $day->toDateString(),
            'period' => 'morning',
            'resource_id' => $desk->id,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Ticket consommé');

    $occupation = DeskOccupation::query()->where('ticket_id', $ticket->id)->firstOrFail();

    expect($occupation->desk_id)->toBe($desk->id)
        ->and($occupation->user_id)->toBe($member->id)
        ->and($occupation->source)->toBe(DeskOccupationSource::ExternalTicket)
        ->and($occupation->created_by)->toBe($this->admin->id)
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Used)
        ->and($ticket->consumed_at)->not->toBeNull()
        ->and($ticket->desk_occupation_id)->toBe($occupation->id);
});

it('consomme manuellement un ticket salle : booking créé + ticket utilisé', function () {
    $member = User::factory()->member()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $member->id,
        'type' => TicketType::MeetingRoomHalfDay->value,
    ]);
    $day = ticketsAdminNextWorkingDay();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make('consume')->table($ticket), [
            'date' => $day->toDateString(),
            'period' => 'afternoon',
            'resource_id' => $room->id,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Ticket consommé');

    $booking = Booking::query()->where('ticket_id', $ticket->id)->firstOrFail();

    expect($booking->resource_id)->toBe($room->id)
        ->and($booking->user_id)->toBe($member->id)
        ->and($booking->created_by)->toBe($this->admin->id)
        ->and($booking->starts_at->format('H:i'))->toBe('14:00')
        ->and($booking->ends_at->format('H:i'))->toBe('18:00')
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Used);
});

it('masque la consommation manuelle sur un ticket déjà utilisé', function () {
    $ticket = Ticket::factory()->used()->create();

    Livewire::test(ListTickets::class)
        ->assertActionHidden(TestAction::make('consume')->table($ticket));
});

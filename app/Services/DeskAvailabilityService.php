<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Exceptions\DomainActionException;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Disponibilité des bureaux pour les nomades external (C7.4, PRD §6.1) :
 *   dispo = bureaux `unassigned` actifs − occupations external présentes.
 * Les bureaux résidents ne sont jamais proposés (même absents) — l'external ne
 * voit que le pool partagé non attitré. Réservation = occupation + ticket.
 */
final class DeskAvailabilityService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TicketService $tickets,
    ) {}

    /**
     * Bureaux non attitrés libres pour une date + demi-journée.
     *
     * @return Collection<int, resource>
     */
    public function availableDesks(CarbonInterface $date, Period $period): Collection
    {
        $occupiedDeskIds = $this->occupiedDeskIds($date, $period);

        return Resource::query()
            ->where('type', ResourceType::Desk->value)
            ->where('assignment', ResourceAssignment::Unassigned->value)
            ->where('is_active', true)
            ->whereNotIn('id', $occupiedDeskIds)
            ->orderBy('display_order')
            ->get();
    }

    public function availableCount(CarbonInterface $date, Period $period): int
    {
        return $this->availableDesks($date, $period)->count();
    }

    /**
     * Réserve un bureau non attitré pour un external : crée l'occupation
     * (source external_ticket, présent) et consomme le ticket, en une
     * transaction verrouillée contre la double réservation du même bureau.
     *
     * @throws DomainActionException si le bureau est occupé / non éligible
     */
    public function bookForExternal(User $user, Resource $desk, CarbonInterface $date, Period $period, Ticket $ticket, ?int $createdBy = null): DeskOccupation
    {
        if ($desk->type !== ResourceType::Desk || $desk->assignment !== ResourceAssignment::Unassigned || ! $desk->is_active) {
            throw new DomainActionException("Ce bureau n'est pas réservable par un nomade.");
        }

        return $this->db->transaction(function () use ($user, $desk, $date, $period, $ticket, $createdBy): DeskOccupation {
            $taken = DeskOccupation::query()
                ->where('desk_id', $desk->id)
                ->whereDate('date', $date->format('Y-m-d'))
                ->where('status', DeskOccupationStatus::Present->value)
                ->whereIn('period', $this->conflictingPeriods($period))
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw new DomainActionException('Ce bureau est déjà occupé sur ce créneau.');
            }

            $occupation = DeskOccupation::create([
                'desk_id' => $desk->id,
                'user_id' => $user->id,
                'date' => $date->format('Y-m-d'),
                'period' => $period->value,
                'source' => DeskOccupationSource::ExternalTicket->value,
                'ticket_id' => $ticket->id,
                'status' => DeskOccupationStatus::Present->value,
                'created_by' => $createdBy,
            ]);

            $this->tickets->consume($ticket, $occupation);

            return $occupation;
        });
    }

    /** Annule une occupation external et restitue le ticket. */
    public function cancelExternal(DeskOccupation $occupation): DeskOccupation
    {
        return $this->db->transaction(function () use ($occupation): DeskOccupation {
            $occupation->status = DeskOccupationStatus::Cancelled;
            $occupation->save();

            if ($occupation->ticket !== null) {
                $this->tickets->restitute($occupation->ticket);
            }

            return $occupation;
        });
    }

    /**
     * IDs des bureaux occupés (external présent) pour une date + demi-journée,
     * en tenant compte des chevauchements matin/après-midi/journée.
     *
     * @return Collection<int, int>
     */
    private function occupiedDeskIds(CarbonInterface $date, Period $period): Collection
    {
        return DeskOccupation::query()
            ->whereDate('date', $date->format('Y-m-d'))
            ->where('source', DeskOccupationSource::ExternalTicket->value)
            ->where('status', DeskOccupationStatus::Present->value)
            ->whereIn('period', $this->conflictingPeriods($period))
            ->pluck('desk_id')
            ->unique()
            ->values();
    }

    /**
     * Périodes qui entrent en conflit avec celle demandée (full_day bloque tout).
     *
     * @return list<string>
     */
    private function conflictingPeriods(Period $period): array
    {
        return match ($period) {
            Period::Morning => [Period::Morning->value, Period::FullDay->value],
            Period::Afternoon => [Period::Afternoon->value, Period::FullDay->value],
            Period::FullDay => [Period::Morning->value, Period::Afternoon->value, Period::FullDay->value],
        };
    }
}

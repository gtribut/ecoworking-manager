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
use App\Support\FrenchHolidays;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
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
        return $this->availableDesksQuery($date, $period)
            ->orderBy('display_order')
            ->get();
    }

    /** Compte en SQL (pas d'hydratation de modèles pour un simple total). */
    public function availableCount(CarbonInterface $date, Period $period): int
    {
        return $this->availableDesksQuery($date, $period)->count();
    }

    /**
     * @return Builder<resource>
     */
    private function availableDesksQuery(CarbonInterface $date, Period $period): Builder
    {
        return Resource::query()
            ->where('type', ResourceType::Desk->value)
            ->where('assignment', ResourceAssignment::Unassigned->value)
            ->where('is_active', true)
            ->whereNotIn('id', $this->occupiedDeskIds($date, $period));
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

        // Décision 2026-07-03 (review finding #17) : les tickets external suivent
        // les jours ouvrés, bureaux comme salles (même règle que
        // RoomAvailabilityService). Point d'étranglement unique : couvre l'API
        // portail ET la consommation manuelle admin.
        if (! FrenchHolidays::isWorkingDay($date)) {
            throw new DomainActionException('Les bureaux nomades ne sont réservables que les jours ouvrés.');
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

            try {
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
            } catch (QueryException $e) {
                // Backstop exclusion `desk_occupations_no_overlap` : créneau gagné
                // par une transaction concurrente (le lock applicatif ne couvre
                // pas le cas « aucune ligne existante »).
                throw $this->isExclusionViolation($e)
                    ? new DomainActionException('Ce bureau est déjà occupé sur ce créneau.')
                    : $e;
            }

            $this->tickets->consume($ticket, $occupation);

            return $occupation;
        });
    }

    /**
     * Annule une occupation external et restitue le ticket. Idempotent (une
     * occupation déjà annulée est renvoyée telle quelle) : la Policy filtre
     * déjà ce cas (`DeskOccupation::scopeCancellable` exige `status=present`),
     * mais l'admin contourne la Policy (super-pouvoir §2.6) — cette méthode ne
     * doit donc jamais restituer deux fois (review lot E pt.1).
     *
     * Défense en profondeur supplémentaire : on ne restitue QUE si le ticket
     * pointe encore réciproquement sur CETTE occupation
     * (`ticket->desk_occupation_id === $occupation->id`). `ticket_id` sur la
     * ligne d'occupation n'est en effet jamais réécrit après une annulation
     * (trace historique dans `desk_occupations` + audit log) alors que le
     * ticket lui-même peut, depuis, avoir été repris par une AUTRE
     * réservation — sans ce garde-fou, une occupation déjà annulée renverrait
     * à tort un ticket qui sert maintenant ailleurs.
     */
    public function cancelExternal(DeskOccupation $occupation): DeskOccupation
    {
        if ($occupation->status === DeskOccupationStatus::Cancelled) {
            return $occupation;
        }

        return $this->db->transaction(function () use ($occupation): DeskOccupation {
            $occupation->status = DeskOccupationStatus::Cancelled;
            $occupation->save();

            $ticket = $occupation->ticket;

            if ($ticket !== null && $ticket->desk_occupation_id === $occupation->id) {
                $this->tickets->restitute($ticket);
            }

            // Le lien est de toute façon rompu : l'occupation est annulée, son
            // ticket (restitué ou déjà repris ailleurs) ne lui appartient plus.
            $occupation->ticket_id = null;
            $occupation->save();

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

    /** SQLSTATE 23P01 = violation d'une contrainte d'exclusion PostgreSQL. */
    private function isExclusionViolation(QueryException $e): bool
    {
        return $e->getCode() === '23P01'
            || str_contains($e->getMessage(), 'desk_occupations_no_overlap');
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

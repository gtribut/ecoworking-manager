<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\DomainActionException;
use App\Models\Booking;
use App\Models\DeskOccupation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Cycle de vie des tickets nomades (C7.5) : consommation à la réservation,
 * restitution à l'annulation, compteurs « Mes tickets ». Les tickets sont
 * NON cessibles (`user_id` immuable) et SANS expiration. data_model §4.2/§6.9.
 *
 * Ne décide pas de l'autorisation (→ TicketPolicy) : oriente uniquement la
 * cohérence métier (statut, type, cible).
 */
final class TicketService
{
    /**
     * Verrouille et renvoie le plus ancien ticket disponible du membre pour ce
     * type, ou lève une erreur métier si le solde est nul. À appeler DANS une
     * transaction (réservation) pour éviter la double consommation concurrente.
     */
    public function lockFirstAvailable(User $user, TicketType $type): Ticket
    {
        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('status', TicketStatus::Available->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($ticket === null) {
            // Libellé métier, jamais la valeur d'enum brute (recette §3.5.3).
            throw new DomainActionException("Vous n'avez plus de ticket « {$type->getLabel()} » disponible.");
        }

        return $ticket;
    }

    /** Solde de tickets disponibles d'un membre pour un type donné. */
    public function availableCount(User $user, TicketType $type): int
    {
        return Ticket::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('status', TicketStatus::Available->value)
            ->count();
    }

    /**
     * Consomme un ticket en le liant à sa cible (réservation salle OU occupation
     * bureau). Vérifie la cohérence statut/type/cible.
     */
    public function consume(Ticket $ticket, Booking|DeskOccupation $target): Ticket
    {
        if ($ticket->status !== TicketStatus::Available) {
            throw new DomainActionException('Ce ticket a déjà été utilisé.');
        }

        $this->assertTypeMatchesTarget($ticket, $target);

        $ticket->status = TicketStatus::Used;
        $ticket->consumed_at = Carbon::now();

        if ($target instanceof Booking) {
            $ticket->booking_id = $target->id;
            $ticket->desk_occupation_id = null;
        } else {
            $ticket->desk_occupation_id = $target->id;
            $ticket->booking_id = null;
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Restitue un ticket consommé (annulation dans les délais) : il redevient
     * disponible et se détache de sa cible. Idempotent si déjà disponible.
     */
    public function restitute(Ticket $ticket): Ticket
    {
        if ($ticket->status !== TicketStatus::Used) {
            return $ticket;
        }

        $ticket->status = TicketStatus::Available;
        $ticket->consumed_at = null;
        $ticket->booking_id = null;
        $ticket->desk_occupation_id = null;
        $ticket->save();

        return $ticket;
    }

    private function assertTypeMatchesTarget(Ticket $ticket, Booking|DeskOccupation $target): void
    {
        $expected = $target instanceof Booking
            ? TicketType::MeetingRoomHalfDay
            : TicketType::DeskHalfDay;

        if ($ticket->type !== $expected) {
            throw new DomainActionException('Le type de ticket ne correspond pas à la réservation.');
        }
    }
}

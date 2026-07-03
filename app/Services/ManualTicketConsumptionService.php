<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Period;
use App\Enums\ResourceType;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\BookingConflictException;
use App\Exceptions\DomainActionException;
use App\Models\Booking;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Consommation manuelle d'un ticket par l'admin (PRD §4.8.1, super-pouvoir
 * métier) : un external se présente sur place, l'admin lui décompte un ticket
 * à la volée. Crée la cible (occupation bureau OU réservation salle) et
 * consomme le ticket dans la MÊME transaction, en réutilisant les gardes
 * métier des services de disponibilité (C7).
 */
final class ManualTicketConsumptionService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DeskAvailabilityService $desks,
        private readonly RoomAvailabilityService $rooms,
        private readonly BookingService $bookings,
    ) {}

    /**
     * Consomme le ticket sur une demi-journée (matin/après-midi) pour la
     * ressource cible : bureau nomade (ticket bureau) ou salle de réunion
     * (ticket salle). `$consumedBy` = admin à l'origine du geste (traçé sur
     * l'occupation/le booking via `created_by`).
     *
     * @throws DomainActionException si le ticket n'est plus disponible ou si la cible n'est pas réservable
     */
    public function consume(Ticket $ticket, Resource $resource, CarbonImmutable $date, Period $period, int $consumedBy): Booking|DeskOccupation
    {
        if ($period === Period::FullDay) {
            throw new DomainActionException('Une consommation manuelle porte sur une demi-journée (matin ou après-midi).');
        }

        return $this->db->transaction(function () use ($ticket, $resource, $date, $period, $consumedBy): Booking|DeskOccupation {
            // Re-lecture verrouillée : évite la double consommation concurrente
            // (le portail et le back-office peuvent viser le même ticket).
            $locked = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TicketStatus::Available) {
                throw new DomainActionException("Ce ticket n'est plus disponible.");
            }

            return match ($locked->type) {
                TicketType::DeskHalfDay => $this->desks->bookForExternal($locked->user, $resource, $date, $period, $locked, $consumedBy),
                TicketType::MeetingRoomHalfDay => $this->consumeForRoom($locked, $resource, $date, $period, $consumedBy),
                default => throw new DomainActionException('Type de ticket non consommable manuellement.'),
            };
        });
    }

    /**
     * Ticket salle → réservation de la demi-journée external (bornes fixes
     * 9h-13h / 14h-18h, jours ouvrés), ticket consommé par BookingService.
     */
    private function consumeForRoom(Ticket $ticket, Resource $room, CarbonImmutable $date, Period $period, int $consumedBy): Booking
    {
        if ($room->type !== ResourceType::MeetingRoom || ! $room->is_active || $room->is_out_of_service) {
            throw new DomainActionException("Cette salle n'est pas réservable.");
        }

        if (! $this->rooms->isExternalSlotBookable($room, $date, $period)) {
            throw new DomainActionException("Ce créneau n'est pas réservable (jour non ouvré ou déjà pris).");
        }

        $bounds = $this->rooms->halfDayBounds($date, $period);

        try {
            return $this->bookings->create([
                'resource' => $room,
                'user' => $ticket->user,
                'starts_at' => $bounds['starts_at'],
                'ends_at' => $bounds['ends_at'],
                'ticket' => $ticket,
                'created_by' => $consumedBy,
            ]);
        } catch (BookingConflictException $e) {
            // Créneau perdu entre le check et l'insertion : erreur métier homogène.
            throw new DomainActionException($e->getMessage());
        }
    }
}

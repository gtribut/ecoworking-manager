<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Réservation de salles (meeting_room / event_room) avec garantie
 * anti-double-booking (C7.1, PRD §5.4 / data_model §6.8).
 *
 * Double protection :
 *  (A) applicative — `lockForUpdate()` sur les réservations confirmées qui
 *      chevauchent le créneau, puis 409 si conflit détecté ;
 *  (D) base — contrainte d'exclusion GiST `bookings_no_overlap` comme backstop
 *      contre la fenêtre de course « créneau vide vu par deux transactions ».
 *
 * Bornes semi-ouvertes : `[starts_at, ends_at)` (la fin d'un créneau peut être
 * le début du suivant).
 */
final class BookingService
{
    /** SQLSTATE PostgreSQL d'une violation de contrainte d'exclusion. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TicketService $tickets,
    ) {}

    /**
     * Crée une réservation de salle confirmée. Si `$ticket` est fourni (résa
     * external payée via ticket), il est consommé dans la même transaction.
     *
     * @param  array{
     *     resource: resource,
     *     user: User,
     *     starts_at: Carbon,
     *     ends_at: Carbon,
     *     title?: ?string,
     *     billable?: ?Model,
     *     is_internal?: bool,
     *     price_ht?: ?string,
     *     vat_rate?: ?string,
     *     ticket?: ?Ticket,
     *     created_by?: ?int,
     * }  $data
     *
     * @throws BookingConflictException si le créneau est déjà pris
     */
    public function create(array $data): Booking
    {
        $resource = $data['resource'];
        $user = $data['user'];
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'];
        $billable = $data['billable'] ?? $user;
        $ticket = $data['ticket'] ?? null;

        return $this->db->transaction(function () use ($resource, $user, $startsAt, $endsAt, $billable, $ticket, $data): Booking {
            // (A) Verrou applicatif : sérialise face à toute résa confirmée chevauchante.
            $hasOverlap = Booking::query()
                ->where('resource_id', $resource->id)
                ->where('status', BookingStatus::Confirmed->value)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw BookingConflictException::forSlot();
            }

            try {
                $booking = Booking::create([
                    'resource_id' => $resource->id,
                    'user_id' => $user->id,
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => $billable->getKey(),
                    'title' => $data['title'] ?? null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => BookingStatus::Confirmed->value,
                    'price_ht' => $data['price_ht'] ?? null,
                    'vat_rate' => $data['vat_rate'] ?? null,
                    'ticket_id' => $ticket?->id,
                    'is_internal' => $data['is_internal'] ?? false,
                    'created_by' => $data['created_by'] ?? null,
                ]);
            } catch (QueryException $e) {
                // (D) Backstop GiST : conflit gagné par une transaction concurrente.
                throw $this->isExclusionViolation($e) ? BookingConflictException::forSlot() : $e;
            }

            if ($ticket !== null) {
                $this->tickets->consume($ticket, $booking);
                $booking->setRelation('ticket', $ticket);
            }

            return $booking;
        });
    }

    /**
     * Annule une réservation : statut `cancelled` + horodatage, et restitue le
     * ticket éventuel (l'autorisation/temporalité est vérifiée en amont par la
     * Policy/Form Request).
     */
    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        return $this->db->transaction(function () use ($booking, $reason): Booking {
            $booking->status = BookingStatus::Cancelled;
            $booking->cancelled_at = Carbon::now();
            $booking->cancel_reason = $reason;
            $booking->save();

            if ($booking->ticket !== null) {
                $this->tickets->restitute($booking->ticket);
            }

            return $booking;
        });
    }

    private function isExclusionViolation(Throwable $e): bool
    {
        return ($e instanceof QueryException)
            && (($e->getCode() === self::EXCLUSION_VIOLATION)
                || str_contains($e->getMessage(), 'bookings_no_overlap'));
    }
}

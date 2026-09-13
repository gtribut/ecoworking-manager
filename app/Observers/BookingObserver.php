<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Enums\NotifiedAction;
use App\Models\Booking;
use App\Notifications\BookingChangedNotification;
use App\Observers\Concerns\NotifiesOwnerOfAdminAction;
use App\Services\TicketService;

/**
 * Cycle de vie réservation.
 *
 * 1. La suppression physique (admin, brouillon d'erreur) doit restituer le
 *    ticket consommé — sinon il reste `used`, orphelin, et le membre a payé
 *    pour rien (review backend M2c).
 * 2. Toute création/modification/annulation faite par un TIERS (le back-office
 *    agissant pour un membre) notifie le propriétaire (lot G, PRD §3.8.4).
 *    Observer plutôt que Resource Filament : couvre tous les chemins d'écriture
 *    (formulaire, action « Annuler », suppression en masse).
 */
final class BookingObserver
{
    use NotifiesOwnerOfAdminAction;

    /**
     * Changements qui intéressent le membre. Une écriture technique (sync
     * Google Calendar, snapshot de prix) ne doit pas encombrer sa cloche.
     */
    private const array NOTIFIABLE_ATTRIBUTES = ['status', 'resource_id', 'starts_at', 'ends_at'];

    public function __construct(private readonly TicketService $tickets) {}

    public function created(Booking $booking): void
    {
        $this->notifyOwner($booking, NotifiedAction::Created);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged(self::NOTIFIABLE_ATTRIBUTES)) {
            return;
        }

        // Passage à « annulée » (BookingService::cancel) : annulation, pas
        // simple modification de créneau.
        $cancelled = $booking->wasChanged('status') && $booking->status === BookingStatus::Cancelled;

        $this->notifyOwner($booking, $cancelled ? NotifiedAction::Removed : NotifiedAction::Updated);
    }

    public function deleting(Booking $booking): void
    {
        if ($booking->ticket !== null) {
            $this->tickets->restitute($booking->ticket);
        }
    }

    public function deleted(Booking $booking): void
    {
        $this->notifyOwner($booking, NotifiedAction::Removed);
    }

    private function notifyOwner(Booking $booking, NotifiedAction $action): void
    {
        $this->ownerToNotify($booking->user)
            ?->notify(new BookingChangedNotification($booking, $action));
    }
}

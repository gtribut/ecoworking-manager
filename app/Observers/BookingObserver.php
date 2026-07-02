<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Booking;
use App\Services\TicketService;

/**
 * Cycle de vie réservation. La suppression physique (admin, brouillon d'erreur)
 * doit restituer le ticket consommé — sinon il reste `used`, orphelin, et le
 * membre a payé pour rien (review backend M2c).
 */
final class BookingObserver
{
    public function __construct(private readonly TicketService $tickets) {}

    public function deleting(Booking $booking): void
    {
        if ($booking->ticket !== null) {
            $this->tickets->restitute($booking->ticket);
        }
    }
}

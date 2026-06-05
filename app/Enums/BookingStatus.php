<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'une réservation de salle (data_model §3, `bookings.status`). */
enum BookingStatus: string
{
    use HasValues;

    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}

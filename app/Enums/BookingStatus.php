<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'une réservation de salle (data_model §3, `bookings.status`). */
enum BookingStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
            self::NoShow => 'Absence (no-show)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }
}

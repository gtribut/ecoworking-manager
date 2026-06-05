<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'une occupation de bureau (data_model §3, `desk_occupations.status`). */
enum DeskOccupationStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Present = 'present';
    case Absent = 'absent';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Cancelled => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'warning',
            self::Cancelled => 'gray',
        };
    }
}

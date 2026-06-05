<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Demi-journée (data_model §3).
 * Utilisé par `desk_occupations.period` et `desk_absences.period`.
 */
enum Period: string implements HasLabel
{
    use HasValues;

    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullDay = 'full_day';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => 'Matin',
            self::Afternoon => 'Après-midi',
            self::FullDay => 'Journée complète',
        };
    }
}

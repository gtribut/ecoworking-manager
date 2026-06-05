<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Demi-journée (data_model §3).
 * Utilisé par `desk_occupations.period` et `desk_absences.period`.
 */
enum Period: string
{
    use HasValues;

    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullDay = 'full_day';
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'une occupation de bureau (data_model §3, `desk_occupations.status`). */
enum DeskOccupationStatus: string
{
    use HasValues;

    case Present = 'present';
    case Absent = 'absent';
    case Cancelled = 'cancelled';
}

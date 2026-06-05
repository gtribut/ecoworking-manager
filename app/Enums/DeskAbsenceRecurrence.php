<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Récurrence d'une absence (data_model §3, `desk_absences.recurrence_type`). */
enum DeskAbsenceRecurrence: string
{
    use HasValues;

    case None = 'none';
    case Weekly = 'weekly';
}

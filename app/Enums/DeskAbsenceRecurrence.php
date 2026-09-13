<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Récurrence d'une absence (data_model §3, `desk_absences.recurrence_type`). */
enum DeskAbsenceRecurrence: string implements HasLabel
{
    use HasValues;

    case None = 'none';
    case Weekly = 'weekly';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Ponctuelle',
            self::Weekly => 'Hebdomadaire',
        };
    }
}

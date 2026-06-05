<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Affectation d'un bureau (data_model §3, `resources.assignment`).
 * Desk uniquement ; NULL pour les salles.
 */
enum ResourceAssignment: string implements HasLabel
{
    use HasValues;

    case AssignedResident = 'assigned_resident';
    case AssignedStaff = 'assigned_staff';
    case Unassigned = 'unassigned';

    public function getLabel(): string
    {
        return match ($this) {
            self::AssignedResident => 'Attitré (résident)',
            self::AssignedStaff => 'Attitré (staff)',
            self::Unassigned => 'Non attitré (flex)',
        };
    }
}

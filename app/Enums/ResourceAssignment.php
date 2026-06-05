<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Affectation d'un bureau (data_model §3, `resources.assignment`).
 * Desk uniquement ; NULL pour les salles.
 */
enum ResourceAssignment: string
{
    use HasValues;

    case AssignedResident = 'assigned_resident';
    case AssignedStaff = 'assigned_staff';
    case Unassigned = 'unassigned';
}

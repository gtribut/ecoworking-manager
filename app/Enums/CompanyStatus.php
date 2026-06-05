<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'une entité (data_model §3, `companies.status`). */
enum CompanyStatus: string
{
    use HasValues;

    case Active = 'active';
    case Inactive = 'inactive';
}

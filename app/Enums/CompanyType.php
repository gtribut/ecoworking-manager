<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Nature d'une entité billable (data_model §3, `companies.entity_type`). */
enum CompanyType: string
{
    use HasValues;

    case Company = 'company';
    case Individual = 'individual';
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Nature d'une entité billable (data_model §3, `companies.entity_type`). */
enum CompanyType: string implements HasLabel
{
    use HasValues;

    case Company = 'company';
    case Individual = 'individual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Company => 'Entreprise',
            self::Individual => 'Particulier',
        };
    }
}

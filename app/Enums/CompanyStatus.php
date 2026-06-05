<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'une entité (data_model §3, `companies.status`). */
enum CompanyStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }
}

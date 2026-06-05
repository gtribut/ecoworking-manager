<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Rôle d'un contact d'entité (data_model §3, `contacts.role`). */
enum ContactRole: string implements HasLabel
{
    use HasValues;

    case Billing = 'billing';
    case Management = 'management';
    case Technical = 'technical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Billing => 'Facturation',
            self::Management => 'Direction',
            self::Technical => 'Technique',
        };
    }
}

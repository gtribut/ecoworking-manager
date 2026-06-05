<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Origine d'une occupation de bureau (data_model §3, `desk_occupations.source`). */
enum DeskOccupationSource: string implements HasLabel
{
    use HasValues;

    case ResidentDefault = 'resident_default';
    case ExternalTicket = 'external_ticket';

    public function getLabel(): string
    {
        return match ($this) {
            self::ResidentDefault => 'Résident (présence par défaut)',
            self::ExternalTicket => 'Externe (ticket)',
        };
    }
}

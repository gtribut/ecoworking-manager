<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Statut d'un ticket (data_model §3, `tickets.status`).
 * Pas de statut `expired` : les tickets n'expirent jamais (§4.2).
 */
enum TicketStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Available = 'available';
    case Used = 'used';
    case Restituted = 'restituted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Used => 'Utilisé',
            self::Restituted => 'Restitué',
            self::Cancelled => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Used => 'info',
            self::Restituted => 'warning',
            self::Cancelled => 'gray',
        };
    }
}

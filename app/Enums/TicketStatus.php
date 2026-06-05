<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Statut d'un ticket (data_model §3, `tickets.status`).
 * Pas de statut `expired` : les tickets n'expirent jamais (§4.2).
 */
enum TicketStatus: string
{
    use HasValues;

    case Available = 'available';
    case Used = 'used';
    case Restituted = 'restituted';
    case Cancelled = 'cancelled';
}

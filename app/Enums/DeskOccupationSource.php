<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Origine d'une occupation de bureau (data_model §3, `desk_occupations.source`). */
enum DeskOccupationSource: string
{
    use HasValues;

    case ResidentDefault = 'resident_default';
    case ExternalTicket = 'external_ticket';
}

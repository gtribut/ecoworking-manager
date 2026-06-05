<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Rôle d'un contact d'entité (data_model §3, `contacts.role`). */
enum ContactRole: string
{
    use HasValues;

    case Billing = 'billing';
    case Management = 'management';
    case Technical = 'technical';
}

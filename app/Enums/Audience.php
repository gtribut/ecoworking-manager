<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Audience / visibilité (data_model §3).
 * Utilisé par `announcements.visibility` et `internal_documents.audience`.
 */
enum Audience: string
{
    use HasValues;

    case All = 'all';
    case Residents = 'residents';
    case Additional = 'additional';
    case BillingContact = 'billing_contact';
}

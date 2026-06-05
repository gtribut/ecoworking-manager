<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Audience / visibilité (data_model §3).
 * Utilisé par `announcements.visibility` et `internal_documents.audience`.
 */
enum Audience: string implements HasLabel
{
    use HasValues;

    case All = 'all';
    case Residents = 'residents';
    case Additional = 'additional';
    case BillingContact = 'billing_contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Tous',
            self::Residents => 'Résidents',
            self::Additional => 'Membres additionnels',
            self::BillingContact => 'Contacts facturation',
        };
    }
}

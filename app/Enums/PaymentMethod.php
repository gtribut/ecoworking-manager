<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Moyen de paiement (data_model §3).
 * Utilisé par `companies.preferred_payment_method` et `payments.method`.
 */
enum PaymentMethod: string implements HasLabel
{
    use HasValues;

    case Sepa = 'sepa';
    case Transfer = 'transfer';
    case Card = 'card';
    case Check = 'check';
    case Cash = 'cash';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sepa => 'Prélèvement SEPA',
            self::Transfer => 'Virement',
            self::Card => 'Carte bancaire',
            self::Check => 'Chèque',
            self::Cash => 'Espèces',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Moyen de paiement (data_model §3).
 * Utilisé par `companies.preferred_payment_method` et `payments.method`.
 */
enum PaymentMethod: string
{
    use HasValues;

    case Sepa = 'sepa';
    case Transfer = 'transfer';
    case Card = 'card';
    case Check = 'check';
    case Cash = 'cash';
}

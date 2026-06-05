<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Périodicité de facturation d'une offre (data_model §3, `offers.billing_period`). */
enum BillingPeriod: string
{
    use HasValues;

    case Monthly = 'monthly';
    case OneTime = 'one_time';
}

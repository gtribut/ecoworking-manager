<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Périodicité de facturation d'une offre (data_model §3, `offers.billing_period`). */
enum BillingPeriod: string implements HasLabel
{
    use HasValues;

    case Monthly = 'monthly';
    case OneTime = 'one_time';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuelle',
            self::OneTime => 'Unique',
        };
    }
}

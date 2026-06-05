<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Type d'offre catalogue (data_model §3, `offers.type`). */
enum OfferType: string
{
    use HasValues;

    case Subscription = 'subscription';
    case OneShot = 'one_shot';
    case Pack = 'pack';
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Type d'offre catalogue (data_model §3, `offers.type`). */
enum OfferType: string implements HasLabel
{
    use HasValues;

    case Subscription = 'subscription';
    case OneShot = 'one_shot';
    case Pack = 'pack';

    public function getLabel(): string
    {
        return match ($this) {
            self::Subscription => 'Abonnement',
            self::OneShot => 'À l\'unité',
            self::Pack => 'Pack',
        };
    }
}

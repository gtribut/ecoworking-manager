<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Nature du souscripteur d'une offre (data_model §3, `offers.subscriber_kind`). */
enum SubscriberKind: string implements HasLabel
{
    use HasValues;

    case Member = 'member';
    case Entity = 'entity';

    public function getLabel(): string
    {
        return match ($this) {
            self::Member => 'Membre',
            self::Entity => 'Entité',
        };
    }
}

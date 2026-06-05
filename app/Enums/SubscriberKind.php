<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Nature du souscripteur d'une offre (data_model §3, `offers.subscriber_kind`). */
enum SubscriberKind: string
{
    use HasValues;

    case Member = 'member';
    case Entity = 'entity';
}

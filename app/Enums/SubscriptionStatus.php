<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'un abonnement (data_model §3, `subscriptions.status`). */
enum SubscriptionStatus: string
{
    use HasValues;

    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Type d'annonce (data_model §3, `announcements.type`). */
enum AnnouncementType: string
{
    use HasValues;

    case Info = 'info';
    case Event = 'event';
    case Alert = 'alert';
}

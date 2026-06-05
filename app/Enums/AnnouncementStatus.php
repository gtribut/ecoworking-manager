<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'une annonce (data_model §3, `announcements.status`). */
enum AnnouncementStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

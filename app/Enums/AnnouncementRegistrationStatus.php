<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'inscription à un event (data_model §3, `announcement_registrations.status`). */
enum AnnouncementRegistrationStatus: string
{
    use HasValues;

    case Registered = 'registered';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';
}

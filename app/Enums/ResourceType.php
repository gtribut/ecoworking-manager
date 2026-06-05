<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Type de ressource (data_model §3, `resources.type`). */
enum ResourceType: string
{
    use HasValues;

    case Desk = 'desk';
    case MeetingRoom = 'meeting_room';
    case EventRoom = 'event_room';
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Type de ressource (data_model §3, `resources.type`). */
enum ResourceType: string implements HasLabel
{
    use HasValues;

    case Desk = 'desk';
    case MeetingRoom = 'meeting_room';
    case EventRoom = 'event_room';

    public function getLabel(): string
    {
        return match ($this) {
            self::Desk => 'Bureau',
            self::MeetingRoom => 'Salle de réunion',
            self::EventRoom => 'Salle événementielle',
        };
    }
}

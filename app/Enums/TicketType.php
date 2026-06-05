<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Type de ticket (data_model §3). Le créneau matin/après-midi est choisi
 * à la réservation, jamais au niveau du type.
 * Utilisé par `offers.ticket_type`, `purchases.ticket_type`, `tickets.type`.
 */
enum TicketType: string implements HasLabel
{
    use HasValues;

    case DeskHalfDay = 'desk_half_day';
    case MeetingRoomHalfDay = 'meeting_room_half_day';

    public function getLabel(): string
    {
        return match ($this) {
            self::DeskHalfDay => 'Bureau — demi-journée',
            self::MeetingRoomHalfDay => 'Salle de réunion — demi-journée',
        };
    }
}

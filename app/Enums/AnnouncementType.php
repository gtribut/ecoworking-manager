<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Type d'annonce (data_model §3, `announcements.type`). */
enum AnnouncementType: string implements HasColor, HasLabel
{
    use HasValues;

    case Info = 'info';
    case Event = 'event';
    case Alert = 'alert';

    public function getLabel(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Event => 'Événement',
            self::Alert => 'Alerte',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Event => 'success',
            self::Alert => 'danger',
        };
    }
}

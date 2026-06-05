<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'un profil membre (data_model §3, `member_profiles.status`). */
enum MemberProfileStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Active = 'active';
    case Paused = 'paused';
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Paused => 'En pause',
            self::Left => 'Parti',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Left => 'gray',
        };
    }
}

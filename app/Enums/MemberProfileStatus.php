<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'un profil membre (data_model §3, `member_profiles.status`). */
enum MemberProfileStatus: string
{
    use HasValues;

    case Active = 'active';
    case Paused = 'paused';
    case Left = 'left';
}

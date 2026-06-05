<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeskOccupation;
use App\Models\User;

/**
 * Isolation des occupations de bureau (PRD §3.4.6). Le membre gère les siennes ;
 * déclarer une présence pour autrui est un super-pouvoir admin (§2.6).
 */
final class DeskOccupationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeskOccupation $occupation): bool
    {
        return $user->isAdmin() || $user->id === $occupation->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DeskOccupation $occupation): bool
    {
        return $user->isAdmin() || $user->id === $occupation->user_id;
    }

    public function delete(User $user, DeskOccupation $occupation): bool
    {
        return $user->isAdmin() || $user->id === $occupation->user_id;
    }
}

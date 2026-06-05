<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MemberProfile;
use App\Models\User;

/**
 * Profil membre : éditable par son propriétaire, visible des autres uniquement
 * via l'annuaire en opt-in (PRD §3.7.5). L'admin a un accès complet.
 */
final class MemberProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MemberProfile $profile): bool
    {
        if ($user->isAdmin() || $user->id === $profile->user_id) {
            return true;
        }

        // Visible des autres membres seulement si opt-in annuaire ET droit annuaire.
        return $profile->show_in_directory
            && $user->can(Permission::ViewAnnuaire->value);
    }

    public function update(User $user, MemberProfile $profile): bool
    {
        return $user->isAdmin() || $user->id === $profile->user_id;
    }
}

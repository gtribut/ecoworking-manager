<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

/**
 * Catalogue des espaces réservables (data_model §4.3). Géré exclusivement par
 * l'admin depuis le back-office. L'exposition des espaces au portail membre
 * (plan, disponibilités) passe par un chemin de lecture dédié (scopes
 * `active`, C4), pas par cette policy. CLAUDE.md §3.1.
 */
final class ResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Resource $resource): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Resource $resource): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $user->isAdmin();
    }
}

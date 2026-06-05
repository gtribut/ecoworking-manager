<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InternalDocument;
use App\Models\User;

/**
 * Documents communs versionnés (charte, CGU, droit image — data_model §4.5).
 * Gérés exclusivement par l'admin. La consultation et la validation côté membre
 * (re-validation à chaque changement de version) passent par un chemin dédié
 * (C5), pas par cette policy. CLAUDE.md §3.1.
 */
final class InternalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }
}

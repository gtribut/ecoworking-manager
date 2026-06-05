<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Annonces (data_model §4.5). Rédigées et gérées exclusivement par l'admin
 * depuis le back-office. La diffusion côté portail (filtrée par `visibility`
 * et `status = published`) passe par un chemin de lecture dédié (C5), pas par
 * cette policy. CLAUDE.md §3.1.
 */
final class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }
}

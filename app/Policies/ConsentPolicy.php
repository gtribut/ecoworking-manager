<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Consent;
use App\Models\User;

/** Isolation des consentements RGPD (data_model §4.1). Strictement personnels. */
final class ConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Consent $consent): bool
    {
        return $user->isAdmin() || $user->id === $consent->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Consent $consent): bool
    {
        return $user->id === $consent->user_id;
    }

    public function delete(User $user, Consent $consent): bool
    {
        return $user->isAdmin();
    }
}

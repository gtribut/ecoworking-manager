<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AnnouncementRegistration;
use App\Models\User;

/** Isolation des inscriptions aux events (PRD §2.5 « s'inscrire aux events »). */
final class AnnouncementRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AnnouncementRegistration $registration): bool
    {
        return $user->isAdmin() || $user->id === $registration->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::RegisterEvent->value);
    }

    public function delete(User $user, AnnouncementRegistration $registration): bool
    {
        return $user->isAdmin() || $user->id === $registration->user_id;
    }
}

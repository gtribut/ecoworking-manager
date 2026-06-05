<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeskAbsence;
use App\Models\User;

/** Isolation des absences (bureau vacant, PRD §3.4.6). Le membre gère les siennes. */
final class DeskAbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeskAbsence $absence): bool
    {
        return $user->isAdmin() || $user->id === $absence->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DeskAbsence $absence): bool
    {
        return $user->isAdmin() || $user->id === $absence->user_id;
    }

    public function delete(User $user, DeskAbsence $absence): bool
    {
        return $user->isAdmin() || $user->id === $absence->user_id;
    }
}

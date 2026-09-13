<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeskAbsence;
use App\Models\User;

/**
 * Isolation des absences (bureau vacant, PRD §3.4.6). Le membre gère les siennes.
 *
 * Accès au module réservé au membre doté d'un bureau attitré (PRD §2.5 :
 * resident/staff ; un `additional` ou un `external` n'a rien à déclarer) —
 * garde d'autorisation, et non plus simple exception métier du PresenceService.
 */
final class DeskAbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasAssignedDesk();
    }

    public function view(User $user, DeskAbsence $absence): bool
    {
        return $user->isAdmin() || $user->id === $absence->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasAssignedDesk();
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

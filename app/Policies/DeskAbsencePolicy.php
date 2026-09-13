<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DeskAbsence;
use App\Models\User;

/**
 * Isolation des absences (bureau vacant, PRD §3.4.6). Le membre gère les siennes.
 *
 * Accès au module réservé au membre doté d'un bureau attitré (PRD §2.5 :
 * resident/staff ; un `additional` ou un `external` n'a rien à déclarer) —
 * garde d'autorisation, et non plus simple exception métier du PresenceService.
 *
 * Côté back-office, la gestion des absences d'autrui est couverte par la
 * permission `declare-presence-for-others` (PRD §2.6 / §4.8.2), détenue par le
 * rôle admin.
 */
final class DeskAbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesForOthers($user) || $user->hasAssignedDesk();
    }

    public function view(User $user, DeskAbsence $absence): bool
    {
        return $this->managesForOthers($user) || $user->id === $absence->user_id;
    }

    public function create(User $user): bool
    {
        return $this->managesForOthers($user) || $user->hasAssignedDesk();
    }

    /**
     * Modification possible « jusqu'au début de l'absence » (PRD §3.4.6), jour
     * de début INCLUS — même borne que la suppression : une borne d'édition
     * plus stricte se contournerait par suppression + re-déclaration (la
     * création accepte `today`). Une absence commencée la veille ou avant n'est
     * plus touchable côté portail : l'admin corrige depuis le back-office
     * (tracé par l'audit log).
     */
    public function update(User $user, DeskAbsence $absence): bool
    {
        if ($this->managesForOthers($user)) {
            return true;
        }

        return $user->id === $absence->user_id && $this->notStartedBefore($absence);
    }

    /** Même fenêtre que la modification (jusqu'au jour de début inclus). */
    public function delete(User $user, DeskAbsence $absence): bool
    {
        if ($this->managesForOthers($user)) {
            return true;
        }

        return $user->id === $absence->user_id && $this->notStartedBefore($absence);
    }

    /** Super-pouvoir back-office : déclarer/corriger l'absence d'un membre. */
    private function managesForOthers(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::DeclarePresenceForOthers->value);
    }

    /**
     * Comparaison de dates CÔTÉ SQL (colonnes DATE) : une ligne fraîchement
     * écrite est relue décalée du fuseau, `isPast()` en PHP mentirait.
     */
    private function notStartedBefore(DeskAbsence $absence): bool
    {
        return DeskAbsence::query()->whereKey($absence->getKey())->notStartedBefore()->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Comptes utilisateurs : gestion 100 % admin (back-office Filament). La création
 * de compte est réservée à l'admin (PRD §3.2 — pas d'inscription self-service).
 * Un membre ne gère jamais d'autres comptes ; il édite son profil via le portail
 * (cf. [[MemberProfilePolicy]]). CLAUDE.md §3.1.
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Soft delete (anonymisation RGPD gérée séparément, §3.4). Un admin ne
        // peut pas se supprimer lui-même (garde-fou contre le lock-out).
        return $user->isAdmin() && $user->isNot($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Anonymisation RGPD (PRD §5.6) : admin uniquement, jamais soi-même
     * (garde-fou lock-out, comme delete), et une seule fois — l'opération
     * est irréversible.
     */
    public function anonymize(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model) && $model->anonymized_at === null;
    }

    public function forceDelete(User $user, User $model): bool
    {
        // Suppression définitive interdite (factures conservées 10 ans, §3.4) :
        // seule l'anonymisation est permise.
        return false;
    }
}

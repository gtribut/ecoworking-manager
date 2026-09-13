<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Http\Controllers\Api\DirectoryController;
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

    /**
     * Lecture de la photo de profil (PRD §3.4.2 / §3.7.3), servie par
     * `GET /api/users/{user}/photo/{size}`. Trois cas seulement : soi-même,
     * un admin, ou un membre habilité à l'annuaire regardant une personne qui
     * s'y affiche volontairement (`show_in_directory`) — même consentement que
     * {@see DirectoryController}, les `external`
     * (sans `view-annuaire`) n'y accèdent donc jamais. Le refus se traduit par
     * un 404 côté contrôleur.
     *
     * Le statut du profil n'est volontairement PAS exigé « actif » : le plan
     * des étages affiche la fiche de tout occupant opt-in, sa photo doit suivre.
     */
    public function viewPhoto(User $user, User $model): bool
    {
        if ($user->is($model) || $user->isAdmin()) {
            return true;
        }

        if (! $user->can(Permission::ViewAnnuaire->value)) {
            return false;
        }

        return $model->memberProfile?->show_in_directory === true;
    }

    public function forceDelete(User $user, User $model): bool
    {
        // Suppression définitive interdite (factures conservées 10 ans, §3.4) :
        // seule l'anonymisation est permise.
        return false;
    }
}

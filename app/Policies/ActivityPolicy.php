<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log (PRD §4.14) : consultation réservée au back-office admin, et
 * STRICTEMENT en lecture seule — aucune écriture/suppression, même admin
 * (c'est le principe d'un journal d'audit). Les entrées sont créées
 * exclusivement par le trait Auditable (spatie/activitylog).
 *
 * Enregistrée manuellement dans AppServiceProvider (le modèle vit dans le
 * namespace Spatie, hors auto-discovery App\Models → App\Policies).
 */
final class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->isAdmin();
    }

    /** Jamais de création manuelle : seules les écritures automatiques existent. */
    public function create(User $user): bool
    {
        return false;
    }

    /** Un audit log ne se modifie pas — même pour un admin. */
    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    /** Un audit log ne se supprime pas — même pour un admin. */
    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }
}

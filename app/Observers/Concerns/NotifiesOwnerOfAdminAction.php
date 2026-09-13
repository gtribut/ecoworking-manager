<?php

declare(strict_types=1);

namespace App\Observers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Garde commune aux notifications « fait POUR vous par le back-office »
 * (réservations, absences — PRD §3.8.4).
 *
 * Le membre ne doit jamais être notifié de sa propre action : la notification
 * ne part que si un acteur est authentifié ET qu'il est différent du
 * propriétaire de la ressource. Hors contexte HTTP (console, worker de queue,
 * seeder) il n'y a pas d'acteur — donc pas de notification, ce qui garde les
 * seeders silencieux.
 */
trait NotifiesOwnerOfAdminAction
{
    /**
     * Propriétaire à notifier, ou `null` s'il ne faut rien envoyer :
     * ressource sans propriétaire (résa interne), action du propriétaire
     * lui-même, absence d'acteur, ou compte anonymisé (CLAUDE.md §3.4).
     */
    private function ownerToNotify(?User $owner): ?User
    {
        $actor = Auth::user();

        if (! $actor instanceof User || $owner === null) {
            return null;
        }

        if ($owner->is($actor) || $owner->anonymized_at !== null) {
            return null;
        }

        return $owner;
    }
}

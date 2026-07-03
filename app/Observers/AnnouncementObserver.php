<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Cycle de vie annonce (C12.3). Notifie l'audience à la publication
 * (PRD §3.8.4 : « Annonce / event publié par l'admin, si visibilité
 * applicable »). Observer plutôt que hook Filament : la notification part
 * quel que soit le chemin d'écriture (Resource admin, seeder, artisan).
 */
final class AnnouncementObserver
{
    /** Annonce créée directement en statut publié. */
    public function created(Announcement $announcement): void
    {
        if ($announcement->status === AnnouncementStatus::Published) {
            $this->notifyAudience($announcement);
        }
    }

    /**
     * Transition vers « publié » (workflow brouillon → publication,
     * PRD §4.11.2). Une simple ré-édition d'une annonce déjà publiée ne
     * re-notifie pas (`wasChanged('status')`).
     */
    public function updated(Announcement $announcement): void
    {
        if ($announcement->wasChanged('status') && $announcement->status === AnnouncementStatus::Published) {
            $this->notifyAudience($announcement);
        }
    }

    /**
     * Destinataires = membres dont un rôle correspond à l'audience
     * (Audience::roles(), même règle que le scope de lecture), hors auteur.
     * Notifications en queue (PortalNotification) : aucun envoi bloquant.
     */
    private function notifyAudience(Announcement $announcement): void
    {
        // whereHas plutôt que le scope spatie `role()` : ce dernier lève
        // RoleDoesNotExist si un rôle n'a pas encore été seedé (tests, install).
        $recipients = User::query()
            ->whereHas('roles', function ($query) use ($announcement): void {
                $query->whereIn('name', $announcement->visibility->roleValues());
            })
            ->when($announcement->created_by !== null, fn ($query) => $query->whereKeyNot($announcement->created_by))
            ->get();

        Notification::send($recipients, new AnnouncementPublishedNotification($announcement));
    }
}

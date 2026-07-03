<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Support\Str;

/**
 * Annonce / event publié par l'admin (PRD §3.8.4, « si visibilité applicable »).
 * In-app uniquement : le doublage email est réservé aux événements critiques
 * (facture émise, document à valider) — une actualité n'en est pas un.
 * Le ciblage par audience est fait en amont (AnnouncementObserver).
 */
final class AnnouncementPublishedNotification extends PortalNotification
{
    protected bool $emailable = false;

    public function __construct(private readonly Announcement $announcement) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $label = $this->announcement->type === AnnouncementType::Event ? 'Nouvel événement' : 'Nouvelle actualité';

        return [
            'type' => 'announcement.published',
            'announcement_id' => $this->announcement->id,
            'announcement_type' => $this->announcement->type->value,
            'title' => $this->announcement->title,
            'message' => "{$label} : ".Str::limit($this->announcement->title, 100),
            'url' => "/announcements/{$this->announcement->id}",
        ];
    }
}

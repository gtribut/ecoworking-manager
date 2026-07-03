<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AnnouncementRegistrationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Annonce exposée au portail (PRD §3.3.2 « Actualités Ecoworking », C12.3).
 * Toujours servie via le scope `visibleTo` (publiée + audience) ; n'expose ni
 * la visibilité ni l'auteur. Le contrôleur charge :
 * - `registrations` filtrées sur l'utilisateur courant → `my_registration_status` ;
 * - `active_registrations_count` (withCount) → jauge des events.
 *
 * @mixin Announcement
 */
final class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registeredCount = $this->active_registrations_count ?? null;
        $spotsLeft = ($this->max_participants !== null && $registeredCount !== null)
            ? max(0, $this->max_participants - $registeredCount)
            : null;

        /** @var AnnouncementRegistration|null $mine */
        $mine = $this->whenLoaded('registrations', fn () => $this->registrations->first(), null);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'published_at' => $this->published_at?->toIso8601String(),
            'event_starts_at' => $this->event_starts_at?->toIso8601String(),
            'event_ends_at' => $this->event_ends_at?->toIso8601String(),
            'location' => $this->location,
            'requires_registration' => $this->requires_registration,
            'max_participants' => $this->max_participants,
            'registered_count' => $registeredCount,
            'spots_left' => $spotsLeft,
            'my_registration_status' => $mine?->status->value,
            'is_registered' => $mine?->status === AnnouncementRegistrationStatus::Registered,
            // L'event accepte-t-il encore des inscriptions (indépendamment de
            // l'état de l'utilisateur) ? Jauge + date recalculées côté back.
            'is_registrable' => $this->acceptsRegistrations()
                && ($this->event_starts_at === null || $this->event_starts_at->isFuture())
                && ($spotsLeft === null || $spotsLeft > 0),
        ];
    }
}

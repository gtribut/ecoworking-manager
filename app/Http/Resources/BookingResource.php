<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation de salle exposée au portail (PRD §3.5.5). Auto-scopée au membre
 * par le contrôleur ; n'expose ni le billable ni les métadonnées internes.
 *
 * @mixin Booking
 */
final class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'resource_name' => $this->whenLoaded('resource', fn () => $this->resource->name),
            'title' => $this->title,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'is_paid' => $this->ticket_id !== null,
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket === null ? null : [
                'id' => $this->ticket->id,
                'type' => $this->ticket->type,
            ]),
            // `startsLater` est renseigné par lot côté contrôleur (comparaison
            // SQL) : jamais `starts_at->isFuture()` en PHP (piège timezone).
            'cancellable' => $this->status->value === 'confirmed' && $this->startsLater === true,
        ];
    }
}

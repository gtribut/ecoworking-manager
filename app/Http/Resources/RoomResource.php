<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ResourceType;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salle du calendrier portail (PRD §3.5.2) : salles de réunion **et** salle
 * événementielle, cette dernière en lecture seule (`is_bookable = false`,
 * PRD §3.5.4 — seul l'admin la réserve). Projection réduite : aucune donnée
 * interne (notes admin, couleur Google Calendar).
 *
 * @mixin Resource
 */
final class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'features' => $this->features ?? [],
            'floor' => $this->floor,
            'external_half_day_price_ht' => $this->external_half_day_price_ht,
            'is_bookable' => self::isBookableByMember($this->resource),
        ];
    }

    /**
     * Une salle est réservable par un membre si c'est une salle de réunion
     * active, en service, et qui n'exige pas l'intervention d'un admin.
     */
    public static function isBookableByMember(Resource $room): bool
    {
        return $room->type === ResourceType::MeetingRoom
            && $room->is_active
            && ! $room->is_out_of_service
            && ! $room->requires_admin;
    }
}

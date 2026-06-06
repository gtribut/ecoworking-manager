<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espace réservable exposé au portail (salle ou bureau). Projection réduite :
 * pas de données internes (assignment, notes admin, couleur calendrier).
 *
 * @mixin Resource
 */
final class ResourceResource extends JsonResource
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
            'svg_desk_id' => $this->svg_desk_id,
            'external_half_day_price_ht' => $this->external_half_day_price_ht,
        ];
    }
}

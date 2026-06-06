<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeskOccupation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Occupation de bureau (réservation nomade external) exposée au portail.
 * Auto-scopée au membre par le contrôleur.
 *
 * @mixin DeskOccupation
 */
final class DeskOccupationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'desk_id' => $this->desk_id,
            'desk_name' => $this->whenLoaded('desk', fn () => $this->desk->name),
            'date' => $this->date?->toDateString(),
            'period' => $this->period,
            'status' => $this->status,
        ];
    }
}

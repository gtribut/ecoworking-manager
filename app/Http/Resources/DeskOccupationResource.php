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
            'desk_floor' => $this->whenLoaded('desk', fn () => $this->desk->floor),
            'date' => $this->date?->toDateString(),
            'period' => $this->period,
            'status' => $this->status,
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket === null ? null : [
                'id' => $this->ticket->id,
                'type' => $this->ticket->type,
            ]),
            // Renseigné PAR LOT côté contrôleur (comparaison SQL) : jamais
            // `date->isFuture()` en PHP (piège timezone, cf. DeskOccupation::scopeCancellable).
            'cancellable' => $this->cancellable === true,
        ];
    }
}

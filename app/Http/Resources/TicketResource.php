<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket nomade exposé au portail (PRD §3.5.6). Le membre ne voit que les siens
 * (auto-scopé par le contrôleur, qui charge `purchase`/`booking.resource`/
 * `deskOccupation.desk` pour éviter les N+1 des accesseurs ci-dessous).
 *
 * @mixin Ticket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            // Date d'achat (purchase.purchased_at) ou de crédit manuel (pas de
            // colonne dédiée pour un geste commercial : created_at du ticket).
            'credited_at' => ($this->purchase?->purchased_at ?? $this->created_at)?->toIso8601String(),
            'consumed_at' => $this->consumed_at?->toIso8601String(),
            'usage' => $this->usage(),
        ];
    }

    /**
     * Cible de consommation (traçabilité, PRD §3.5.6/§3.5.9) : la résa salle
     * OU l'occupation bureau, jamais les deux (TicketService::consume).
     *
     * @return array<string, mixed>|null
     */
    private function usage(): ?array
    {
        if ($this->booking !== null) {
            return [
                'kind' => 'booking',
                'resource_name' => $this->booking->resource?->name,
                'date' => $this->booking->starts_at?->toDateString(),
            ];
        }

        if ($this->deskOccupation !== null) {
            return [
                'kind' => 'desk_occupation',
                'resource_name' => $this->deskOccupation->desk?->name,
                'date' => $this->deskOccupation->date?->toDateString(),
            ];
        }

        return null;
    }
}

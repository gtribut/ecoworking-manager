<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket nomade exposé au portail (PRD §3.5.6). Le membre ne voit que les siens
 * (auto-scopé par le contrôleur).
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
            'consumed_at' => $this->consumed_at?->toIso8601String(),
        ];
    }
}

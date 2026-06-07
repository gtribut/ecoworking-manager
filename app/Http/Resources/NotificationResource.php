<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Sérialisation d'une notification in-app (table `notifications`, driver
 * database). Expose la charge utile métier (`data`) + l'état lu/non lu.
 *
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

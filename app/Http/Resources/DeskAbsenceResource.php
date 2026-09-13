<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeskAbsence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Absence déclarée par un résident sur son bureau attitré (PRD §3.4.6).
 * Auto-scopée au membre par le contrôleur.
 *
 * @mixin DeskAbsence
 */
final class DeskAbsenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date_start' => $this->date_start?->toDateString(),
            'date_end' => $this->date_end?->toDateString(),
            'period' => $this->period,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_day_of_week' => $this->recurrence_day_of_week,
            'notes' => $this->notes,
            // Fenêtres d'action du MEMBRE, calculées en SQL par le contrôleur
            // (jamais `isPast()` en PHP : piège fuseau du dépôt) : édition
            // jusqu'à la veille du début, suppression jusqu'au début inclus.
            'can_edit' => $this->resource->canEdit ?? false,
            'can_delete' => $this->resource->canDelete ?? false,
        ];
    }
}

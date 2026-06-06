<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\DeskOccupation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réservation d'un bureau nomade par un external (PRD §3.5.9). Une demi-journée
 * ou la journée, consommant un ticket `desk_half_day`. Auto-scopé au membre.
 */
final class StoreDeskOccupationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DeskOccupation::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'desk_id' => ['required', 'integer', 'exists:resources,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'period' => ['required', 'string', 'in:morning,afternoon,full_day'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\DeskOccupation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Disponibilité des bureaux nomades (PRD §3.5.9). Réservée à qui peut
 * réserver un bureau — `create-paid-booking` (external) ou admin, même garde
 * que la liste des occupations (`DeskOccupationPolicy::viewAny`) : un
 * resident/additional/billing_contact n'a rien à faire sur cet écran
 * (review lot E pt.3 — l'endpoint n'était gardé que par `auth:sanctum`).
 */
final class IndexDeskAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DeskOccupation::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'period' => ['required', 'string', 'in:morning,afternoon,full_day'],
        ];
    }
}

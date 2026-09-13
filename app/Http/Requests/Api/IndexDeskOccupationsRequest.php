<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\DeskOccupation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Liste des occupations de bureau nomade du membre connecté (PRD §3.5.9,
 * pendant de « Mes prochaines réservations » §3.5.7). Auto-scopée par le
 * contrôleur ; l'accès au module est réservé à l'external (ou l'admin), cf.
 * `DeskOccupationPolicy::viewAny`.
 */
final class IndexDeskOccupationsRequest extends FormRequest
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
            'upcoming' => ['sometimes', 'boolean'],
            'past' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}

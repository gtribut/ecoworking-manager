<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\DeskAbsence;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une absence depuis le portail (PRD §3.4.6). Mêmes règles que
 * la déclaration : la fenêtre d'édition (propriétaire + absence pas encore
 * commencée) est portée par `DeskAbsencePolicy::update`, comparée côté SQL.
 */
final class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof DeskAbsence
            && $this->user()?->can('update', $absence) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_start' => ['required', 'date', 'after_or_equal:today'],
            // Récurrence hebdo BORNABLE (PRD §3.4.6) : la fin s'applique aussi
            // au mode `weekly` (« tous les vendredis du 1er juin au 30 sept. »).
            // Facultative : une récurrence sans terme reste tolérée.
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'period' => ['nullable', 'string', 'in:morning,afternoon,full_day'],
            'recurrence_type' => ['nullable', 'string', 'in:none,weekly'],
            'recurrence_day_of_week' => ['nullable', 'integer', 'between:0,6', 'required_if:recurrence_type,weekly'],
            // Note libre visible de l'admin et du seul auteur (PRD §3.4.6).
            // 255 = largeur de la colonne `desk_absences.notes` (data_model §4.3).
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

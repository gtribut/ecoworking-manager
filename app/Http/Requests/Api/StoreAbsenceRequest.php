<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\DeskAbsence;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Déclaration d'absence d'un résident sur son bureau attitré (PRD §3.4.6) :
 * jour unique, plage, ou récurrence hebdomadaire. Auto-scopé au membre (le
 * bureau est déduit de son profil, jamais fourni par le client).
 */
final class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DeskAbsence::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_start' => ['required', 'date', 'after_or_equal:today'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'period' => ['nullable', 'string', 'in:morning,afternoon,full_day'],
            'recurrence_type' => ['nullable', 'string', 'in:none,weekly'],
            'recurrence_day_of_week' => ['nullable', 'integer', 'between:0,6', 'required_if:recurrence_type,weekly'],
        ];
    }
}

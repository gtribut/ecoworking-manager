<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Disponibilité multi-salles du calendrier (PRD §3.5.2) : plage de dates
 * bornée + filtre optionnel de salles. Accès réservé aux détenteurs de
 * `view-bookings-calendar` (le contact facturation pur en est exclu, PRD §2.5).
 */
final class IndexRoomAvailabilityRequest extends FormRequest
{
    /** Plage maximale demandable : une semaine affichée + 1 jour de marge. */
    public const int MAX_DAYS = 8;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isAdmin() || $user->can(Permission::ViewBookingsCalendar->value));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'rooms' => ['sometimes', 'array', 'max:20'],
            'rooms.*' => ['integer', 'exists:resources,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $this->from()->diffInDays($this->to()) + 1 > self::MAX_DAYS) {
                $validator->errors()->add(
                    'to',
                    'La plage demandée ne peut pas dépasser '.self::MAX_DAYS.' jours.',
                );
            }
        }];
    }

    /** Premier jour affiché (00:00 locale). */
    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('from')->toString())->startOfDay();
    }

    /** Dernier jour affiché (00:00 locale ; la borne haute exclusive est `to + 1 jour`). */
    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('to')->toString())->startOfDay();
    }

    /**
     * Salles demandées (vide = toutes les salles du calendrier).
     *
     * @return list<int>
     */
    public function roomIds(): array
    {
        /** @var list<int> */
        return array_values(array_map(intval(...), (array) $this->input('rooms', [])));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Permission;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réservation de salle côté portail (PRD §3.5.5).
 *
 * Deux formes selon le rôle :
 *  - résident/staff/additional : créneau libre (`starts_at` / `ends_at`), gratuit ;
 *  - external : demi-journée fixe (`date` + `period`), payée via ticket.
 *
 * Le membre ne réserve que pour lui-même (auto-scopé : aucun `user_id` accepté).
 */
final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isExternalBooker()) {
            $rules['date'] = ['required', 'date', 'after_or_equal:today'];
            $rules['period'] = ['required', 'string', 'in:morning,afternoon'];
        } else {
            $rules['starts_at'] = ['required', 'date', 'after:now'];
            $rules['ends_at'] = ['required', 'date', 'after:starts_at'];
        }

        return $rules;
    }

    /** L'utilisateur réserve-t-il en tant qu'external (ticket payant) ? */
    public function isExternalBooker(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can(Permission::CreatePaidBooking->value)
            && ! $user->can(Permission::CreateOwnBooking->value);
    }
}

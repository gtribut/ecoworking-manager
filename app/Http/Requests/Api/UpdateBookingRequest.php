<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une réservation de salle depuis le portail (PRD §3.5.5).
 *
 * Mêmes règles que la création (`StoreBookingRequest`) : créneau libre pour
 * resident/staff/additional, demi-journée fixe pour l'external. L'autorisation
 * (propriétaire + créneau pas encore commencé + `manage-own-booking`) est
 * portée par `BookingPolicy::update`.
 */
final class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && $this->user()?->can('update', $booking) === true;
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
        return $this->user()?->booksAsExternal() === true;
    }
}

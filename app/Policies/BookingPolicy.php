<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;

/**
 * Isolation des réservations (PRD §2.5, §3.5.5). Un membre n'agit que sur ses
 * propres réservations ; l'admin sur toutes (super-pouvoir §2.6).
 */
final class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::ViewOwnBookings->value);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $user->id === $booking->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->can(Permission::CreateOwnBooking->value)
            || $user->can(Permission::CreatePaidBooking->value);
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $booking->user_id
            && $user->can(Permission::ManageOwnBooking->value)
            && $booking->status !== BookingStatus::Cancelled
            && $this->startsLater($booking);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }

    /**
     * Le créneau n'a-t-il pas encore commencé (délai d'annulation Q22 : jusqu'à
     * l'heure de début) ? Comparaison côté SQL : une ligne fraîchement écrite
     * est relue décalée du fuseau, `starts_at->isFuture()` en PHP mentirait.
     */
    private function startsLater(Booking $booking): bool
    {
        return Booking::query()->whereKey($booking->getKey())->startsLater()->exists();
    }
}

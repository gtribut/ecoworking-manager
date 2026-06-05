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
            && $booking->status !== BookingStatus::Cancelled
            && $booking->starts_at?->isFuture() === true;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }
}

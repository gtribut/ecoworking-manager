<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DeskOccupation;
use App\Models\User;

/**
 * Isolation des occupations de bureau (PRD §3.4.6 / §3.5.9). Le membre gère
 * les siennes ; déclarer une présence pour autrui est un super-pouvoir admin
 * (§2.6). `viewAny`/`create` alignés sur `create-paid-booking` (external) —
 * seul rôle qui réserve un bureau nomade via ticket (écart relevé lot C).
 */
final class DeskOccupationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::CreatePaidBooking->value);
    }

    public function view(User $user, DeskOccupation $occupation): bool
    {
        return $user->isAdmin() || $user->id === $occupation->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::CreatePaidBooking->value);
    }

    public function update(User $user, DeskOccupation $occupation): bool
    {
        return $user->isAdmin() || $user->id === $occupation->user_id;
    }

    /**
     * Annulation : propriétaire + délai non dépassé (cf. `DeskOccupation::scopeCancellable`,
     * même logique que `BookingPolicy::startsLater` transposée aux demi-journées).
     */
    public function delete(User $user, DeskOccupation $occupation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $occupation->user_id && $this->isCancellable($occupation);
    }

    private function isCancellable(DeskOccupation $occupation): bool
    {
        return DeskOccupation::query()->whereKey($occupation->getKey())->cancellable()->exists();
    }
}

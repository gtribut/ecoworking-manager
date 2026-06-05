<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/** Isolation des achats ponctuels (data_model §4.2). Lecture propriétaire ; gestion admin. */
final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin() || $user->id === $purchase->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin();
    }
}

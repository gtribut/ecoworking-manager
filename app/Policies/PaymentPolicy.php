<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Paiements : enregistrés et consultés exclusivement par l'admin (PRD §2.6).
 * Le membre ne voit que le **statut** de paiement de ses factures, pas les
 * lignes de paiement elles-mêmes.
 */
final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}

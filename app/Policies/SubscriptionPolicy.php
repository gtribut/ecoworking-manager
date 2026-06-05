<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * Abonnements (data_model §4.2). Le souscripteur voit le sien ; le `billing_contact`
 * de l'entité billable voit les abonnements facturés à cette entité. Gestion admin only.
 */
final class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $subscriber = $subscription->subscriber;
        if ($subscriber instanceof User && $subscriber->is($user)) {
            return true;
        }

        return $user->isBillingContact() && $user->canBillFor($subscription->billable);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }
}

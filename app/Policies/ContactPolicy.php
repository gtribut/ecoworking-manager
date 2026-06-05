<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

/**
 * Contacts d'entité (facturation, direction, technique) : données de gestion
 * back-office, administrées exclusivement par l'admin. Non exposés au portail
 * membre en MVP. CLAUDE.md §3.1. Cf. [[CompanyPolicy]].
 */
final class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->isAdmin();
    }
}

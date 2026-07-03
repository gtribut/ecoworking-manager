<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Isolation des tickets nomades (PRD §3.5.6). Lecture par le propriétaire ;
 * la consommation pour autrui est un super-pouvoir admin (§2.6).
 */
final class TicketPolicy
{
    /**
     * Liste de TOUS les tickets = back-office uniquement (TicketResource C12.2).
     * Le membre voit les siens via l'API portail auto-scopée (TicketController),
     * qui ne passe pas par viewAny.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $user->id === $ticket->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}

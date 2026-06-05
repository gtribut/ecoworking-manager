<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

/**
 * Catalogue des prestations (data_model §4.2). Géré exclusivement par l'admin.
 * La mise à jour d'un prix s'applique à tous dès validation (pas de prix figé
 * côté abonné, §3.6 / §6.7). L'exposition des offres publiques au portail membre
 * passe par un chemin de lecture dédié (scopes `public`/`active`, C4), pas par
 * cette policy. CLAUDE.md §3.1.
 */
final class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }
}

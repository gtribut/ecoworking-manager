<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InternalDocument;
use App\Models\User;

/**
 * Documents communs versionnés (charte, CGU, droit image — data_model §4.5).
 * Gérés exclusivement par l'admin. La consultation liste côté membre passe par
 * le scope `applicableTo` (C12.4) ; le téléchargement du PDF exerce `download`
 * ci-dessous. CLAUDE.md §3.1.
 */
final class InternalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }

    /**
     * Téléchargement du PDF côté portail (C12.4) : réservé aux membres couverts
     * par l'audience d'un document actif et publié (PRD §3.3.2).
     */
    public function download(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin() || $document->isApplicableTo($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, InternalDocument $document): bool
    {
        return $user->isAdmin();
    }
}

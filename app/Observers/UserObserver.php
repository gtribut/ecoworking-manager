<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Auth\MagicLinkService;

/**
 * Observer User — invalidation des magic links au changement de mot de passe
 * (C12.8a, PRD §3.2). Placé au niveau modèle (et non dans les seules actions
 * Fortify) pour couvrir TOUS les chemins : update par le membre, reset par
 * email, modification par un admin via Filament.
 */
final class UserObserver
{
    public function __construct(private readonly MagicLinkService $magicLinks) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged('password')) {
            $this->magicLinks->invalidateFor($user);
        }
    }
}

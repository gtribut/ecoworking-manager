<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Entité juridique. Consultation en lecture seule ouverte à tout membre rattaché
 * (PRD §2.5 « Voir son entité juridique ») ; toute modification est admin only
 * (le membre passe par « Demander une modification », PRD §3.6.4).
 */
final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->linkedCompanyIds()->contains($company->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }
}

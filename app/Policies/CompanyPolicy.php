<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
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

    /**
     * Module administratif du portail (PRD §3.6.4) : seul le `billing_contact`
     * accède au bloc « Mon entreprise » / « Mes données de facturation ».
     * Sans entité rattachée, la liste renvoyée est simplement vide.
     */
    public function viewAnyBillingDetails(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::ViewBillingSection->value);
    }

    /**
     * Détail de facturation d'UNE entité (mode de paiement, IBAN-4) : rôle
     * `billing_contact` **et mandat explicite sur cette entité**
     * (`contacts.role = billing`), pas le simple rattachement de membre
     * (CLAUDE.md §3.1). Sans ça, un contact facturation d'Alpha, résident de
     * Beta, lirait les coordonnées bancaires de Beta.
     */
    public function viewBillingDetails(User $user, Company $company): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isBillingContact()
            && $user->billingContactCompanyIds()->contains($company->getKey());
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

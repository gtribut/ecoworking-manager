<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AdministrativeDocument;
use App\Models\User;

/**
 * Documents administratifs d'une entité (PRD §3.6.3). Visibles du `billing_contact`
 * de l'entité concernée ; gérés exclusivement par l'admin.
 */
final class AdministrativeDocumentPolicy
{
    /** Section « Mes documents administratifs » du portail (PRD §3.6.3). */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::ViewEntityAdminDocuments->value);
    }

    public function view(User $user, AdministrativeDocument $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isBillingContact()
            && $document->company !== null
            && $user->canBillFor($document->company);
    }

    public function download(User $user, AdministrativeDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AdministrativeDocument $document): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AdministrativeDocument $document): bool
    {
        return $user->isAdmin();
    }
}

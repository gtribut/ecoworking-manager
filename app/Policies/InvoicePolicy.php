<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

/**
 * Facturation (PRD §3.6, CLAUDE.md §3.6). Visibilité réservée au `billing_contact`
 * de l'entité concernée. Règle dure : une facture émise ne se supprime JAMAIS —
 * seuls les brouillons (sans numéro) sont supprimables, et par l'admin seul.
 */
final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isBillingContact() && $user->canBillFor($invoice->billable);
    }

    /** Téléchargement du PDF — même périmètre que la consultation (PRD §3.6.2). */
    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        // Une facture émise est figée (CLAUDE.md §3.6) ; seuls les brouillons s'éditent.
        return $user->isAdmin() && $invoice->status === InvoiceStatus::Draft;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        // Interdit légalement sur une facture émise — annulation + avoir uniquement.
        return $user->isAdmin() && $invoice->status === InvoiceStatus::Draft;
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLineSubscription;

/**
 * Cycle de vie facture. Seuls les brouillons sont supprimables
 * (InvoicePolicy::delete) — et la suppression est un SOFT delete : lignes et
 * liaisons d'idempotence survivent en base.
 */
final class InvoiceObserver
{
    /**
     * Purge les liaisons d'idempotence du brouillon supprimé : sans cela,
     * `MonthlyBillingService::alreadyBilled` (et le UNIQUE DB) considèrent la
     * période comme facturée pour toujours — l'entité devient silencieusement
     * infacturable sur le mois.
     */
    public function deleting(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return;
        }

        InvoiceLineSubscription::query()
            ->whereIn('invoice_line_id', $invoice->lines()->select('id'))
            ->delete();
    }
}

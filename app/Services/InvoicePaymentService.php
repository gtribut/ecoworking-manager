<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Observers\PaymentObserver;

/**
 * Recalcul du montant réglé et du statut de paiement d'une facture (C6.6).
 * Déclenché par l'{@see PaymentObserver} à chaque
 * création/suppression/restauration de paiement. Les montants des LIGNES
 * restent figés (§3.6) ; seuls `amount_paid` et le `status` évoluent.
 */
final class InvoicePaymentService
{
    public function recalculate(Invoice $invoice): void
    {
        $paid = (float) $invoice->payments()->sum('amount');

        $invoice->amount_paid = number_format($paid, 2, '.', '');
        $invoice->status = $this->resolveStatus($invoice, $paid);
        // save() (pas saveQuietly) : les transitions sent → partially_paid →
        // paid doivent apparaître dans l'audit log (§3.4).
        $invoice->save();
    }

    private function resolveStatus(Invoice $invoice, float $paid): InvoiceStatus
    {
        // Statuts terminaux / hors cycle de paiement : inchangés.
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)
            || $invoice->is_credit_note) {
            return $invoice->status;
        }

        $total = (float) $invoice->total_ttc;

        if ($total > 0 && $paid >= $total) {
            return InvoiceStatus::Paid;
        }

        // Échue et non soldée : EN RETARD, même partiellement payée — sinon le
        // cron la rebasculerait `overdue` et renotifierait à chaque paiement
        // partiel. La notification est tracée à part (`overdue_notified_at`).
        if ($this->isOverdue($invoice)) {
            return InvoiceStatus::Overdue;
        }

        return $paid > 0 ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Sent;
    }

    private function isOverdue(Invoice $invoice): bool
    {
        return $invoice->due_at !== null && $invoice->due_at->endOfDay()->isPast();
    }
}

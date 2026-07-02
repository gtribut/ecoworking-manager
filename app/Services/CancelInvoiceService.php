<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Annulation d'une facture émise (CLAUDE.md §3.6). Une facture émise ne se
 * supprime JAMAIS : la seule correction légale est le passage `cancelled` +
 * l'émission d'un **avoir** (facture miroir à montants négatifs) qui consomme
 * lui aussi le compteur. Les deux factures restent liées (self-FK).
 */
final class CancelInvoiceService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly InvoiceNumberingService $numbering,
    ) {}

    /**
     * Annule la facture et émet son avoir. Retourne l'avoir créé.
     *
     * @throws RuntimeException si la facture n'est pas émise / déjà annulée / est un avoir
     */
    public function cancel(Invoice $invoice, ?string $reason = null, ?int $emittedBy = null): Invoice
    {
        $creditNote = $this->db->transaction(function () use ($invoice, $reason, $emittedBy): Invoice {
            // Verrou + re-lecture DANS la transaction : deux annulations
            // concurrentes de la même facture émettraient deux avoirs numérotés
            // (sur-crédit comptable).
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status === InvoiceStatus::Draft || $invoice->number === null) {
                throw new RuntimeException('Un brouillon se supprime, il ne s\'annule pas.');
            }

            if ($invoice->status === InvoiceStatus::Cancelled) {
                throw new RuntimeException('Facture déjà annulée.');
            }

            if ($invoice->is_credit_note) {
                throw new RuntimeException('Un avoir ne s\'annule pas.');
            }

            $invoice->loadMissing('lines');

            $issuedAt = now();

            $creditNote = new Invoice;
            $creditNote->forceFill([
                'number' => $this->numbering->nextNumber((int) $issuedAt->year),
                'billable_type' => $invoice->billable_type,
                'billable_id' => $invoice->billable_id,
                'status' => InvoiceStatus::Sent,
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $issuedAt->toDateString(),
                'billing_name' => $invoice->billing_name,
                'billing_address' => $invoice->billing_address,
                'billing_siret' => $invoice->billing_siret,
                'billing_vat_number' => $invoice->billing_vat_number,
                'subtotal_ht' => $this->negate($invoice->subtotal_ht),
                'total_vat' => $this->negate($invoice->total_vat),
                'total_ttc' => $this->negate($invoice->total_ttc),
                'amount_paid' => 0,
                'is_credit_note' => true,
                'credit_note_for_invoice_id' => $invoice->id,
                'notes' => $reason,
                'emitted_by' => $emittedBy,
            ]);
            $creditNote->save();

            // Lignes miroir à montants négatifs (prix unitaire inversé → totaux figés négatifs).
            foreach ($invoice->lines as $line) {
                $creditNote->lines()->create([
                    'related_type' => $line->related_type,
                    'related_id' => $line->related_id,
                    'description' => 'Avoir — '.$line->description,
                    'quantity' => $line->quantity,
                    'unit_price_ht' => $this->negate($line->unit_price_ht),
                    'discount_rate' => $line->discount_rate,
                    'vat_rate' => $line->vat_rate,
                    'line_total_ht' => $this->negate($line->line_total_ht),
                    'line_vat' => $this->negate($line->line_vat),
                    'line_total_ttc' => $this->negate($line->line_total_ttc),
                    'period_start' => $line->period_start,
                    'period_end' => $line->period_end,
                ]);
            }

            $invoice->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => $issuedAt,
                'cancellation_credit_note_id' => $creditNote->id,
            ]);
            $invoice->save();

            return $creditNote;
        });

        // L'avoir est une pièce comptable au même titre que la facture : son PDF
        // est généré dès l'émission (même flux que IssueInvoiceService).
        GenerateInvoicePdfJob::dispatch($creditNote->id)->afterCommit();

        return $creditNote;
    }

    private function negate(int|float|string|null $amount): string
    {
        return number_format(-1 * (float) $amount, 2, '.', '');
    }
}

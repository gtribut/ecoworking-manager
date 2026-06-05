<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Émission définitive d'une facture (CLAUDE.md §3.6). Pose le numéro (consommé
 * ici seulement), fige les totaux depuis les lignes et snapshote l'adresse de
 * facturation. Après émission, la facture est figée : plus aucune édition de
 * montant n'est permise (InvoicePolicy::update ⇒ draft uniquement).
 */
final class IssueInvoiceService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly InvoiceNumberingService $numbering,
    ) {}

    /**
     * @throws RuntimeException si la facture n'est pas un brouillon
     */
    public function issue(Invoice $invoice, ?int $emittedBy = null): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new RuntimeException('Seul un brouillon peut être émis.');
        }

        return $this->db->transaction(function () use ($invoice, $emittedBy): Invoice {
            $invoice->loadMissing(['lines', 'billable']);

            // Recalcul défensif des totaux depuis les lignes (jamais le front, §3.6).
            $subtotalHt = 0.0;
            $totalVat = 0.0;
            $totalTtc = 0.0;
            foreach ($invoice->lines as $line) {
                $subtotalHt += (float) $line->line_total_ht;
                $totalVat += (float) $line->line_vat;
                $totalTtc += (float) $line->line_total_ttc;
            }

            $snapshot = $this->billingSnapshot($invoice);

            $issuedAt = now();

            $invoice->forceFill([
                'number' => $this->numbering->nextNumber((int) $issuedAt->year),
                'status' => InvoiceStatus::Sent,
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $issuedAt->copy()->addDays(14)->toDateString(),
                'subtotal_ht' => number_format($subtotalHt, 2, '.', ''),
                'total_vat' => number_format($totalVat, 2, '.', ''),
                'total_ttc' => number_format($totalTtc, 2, '.', ''),
                'billing_name' => $snapshot['billing_name'],
                'billing_address' => $snapshot['billing_address'],
                'billing_siret' => $snapshot['billing_siret'],
                'billing_vat_number' => $snapshot['billing_vat_number'],
                'emitted_by' => $emittedBy ?? $invoice->emitted_by,
            ]);
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Snapshot de l'adresse de facturation, figé à l'émission.
     *
     * @return array{billing_name: ?string, billing_address: ?array<string, mixed>, billing_siret: ?string, billing_vat_number: ?string}
     */
    private function billingSnapshot(Invoice $invoice): array
    {
        $billable = $invoice->billable;

        if ($billable instanceof Company) {
            return [
                'billing_name' => $billable->name,
                'billing_address' => [
                    'line1' => $billable->address_line1,
                    'line2' => $billable->address_line2,
                    'postal_code' => $billable->postal_code,
                    'city' => $billable->city,
                    'country' => $billable->country,
                ],
                'billing_siret' => $billable->siret,
                'billing_vat_number' => $billable->vat_number,
            ];
        }

        if ($billable instanceof User) {
            return [
                'billing_name' => $billable->fullName(),
                'billing_address' => null,
                'billing_siret' => null,
                'billing_vat_number' => null,
            ];
        }

        return [
            'billing_name' => $invoice->billing_name,
            'billing_address' => $invoice->billing_address,
            'billing_siret' => $invoice->billing_siret,
            'billing_vat_number' => $invoice->billing_vat_number,
        ];
    }
}

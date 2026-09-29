<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture exposée au portail membre (PRD §3.6.2). Projection volontairement
 * réduite : numéro, statut, dates, montants figés. Le détail des lignes et la
 * gestion (émission/avoir) restent côté admin. `pdf_available` indique si le
 * PDF est téléchargeable (généré et stocké). `amount_due` = reste à payer
 * calculé ici (jamais côté SPA, CLAUDE.md §3.6), `null` quand il n'y a rien à
 * régler : facture soldée, annulée ou avoir (R-12).
 *
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'is_credit_note' => $this->is_credit_note,
            'issued_at' => $this->issued_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'total_ht' => $this->subtotal_ht,
            'total_vat' => $this->total_vat,
            'total_ttc' => $this->total_ttc,
            'amount_paid' => $this->amount_paid,
            'amount_due' => $this->amountDue(),
            'pdf_available' => $this->pdf_path !== null,
        ];
    }

    private function amountDue(): ?string
    {
        if ($this->is_credit_note || $this->status === InvoiceStatus::Cancelled) {
            return null;
        }

        $due = bcsub((string) $this->total_ttc, (string) $this->amount_paid, 2);

        return bccomp($due, '0', 2) > 0 ? $due : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture exposée au portail membre (PRD §3.6.2). Projection volontairement
 * réduite : numéro, statut, dates, montants figés. Le détail des lignes et la
 * gestion (émission/avoir) restent côté admin. `pdf_available` indique si le
 * PDF est téléchargeable (généré et stocké).
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
            'pdf_available' => $this->pdf_path !== null,
        ];
    }
}

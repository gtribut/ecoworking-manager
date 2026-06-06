<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Génération et stockage du PDF d'une facture / d'un avoir (C6.2, PRD §5.7,
 * ADR-0005 stratégie B — PDF classique en MVP, Factur-X en V2).
 *
 * Stocké sur le disque par défaut (Cellar/S3 en prod, local en dev) au chemin
 * `invoices/{année}/{numéro}.pdf`, lu ensuite par l'API portail (C4.3).
 */
final class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'creditNoteForInvoice']);

        $path = $this->path($invoice);

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4');

        Storage::put($path, $pdf->output());

        $invoice->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    private function path(Invoice $invoice): string
    {
        $year = $invoice->issued_at?->year ?? now()->year;
        $name = $invoice->number ?? "brouillon-{$invoice->id}";

        return "invoices/{$year}/{$name}.pdf";
    }
}

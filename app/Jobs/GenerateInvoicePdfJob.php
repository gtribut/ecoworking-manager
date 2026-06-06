<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Génère le PDF d'une facture émise hors du cycle requête (PRD §5.7 : étape de
 * génération en file). Dispatché après l'émission définitive.
 */
final class GenerateInvoicePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $invoiceId) {}

    public function handle(InvoicePdfService $pdf): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);

        if ($invoice === null || $invoice->number === null) {
            return; // brouillon supprimé / non émis : rien à générer
        }

        $pdf->generate($invoice);
    }
}

<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceLine;

/**
 * C6.2 — mentions légales du gabarit de facture (CGI art. 289 / 242 nonies A,
 * pénalités art. L441-10). On rend la vue Blade `invoices.pdf` en HTML (sans
 * dompdf : le binaire PDF n'est pas assertable) — review 06 moyen 3.
 */
function renderInvoicePdfView(Invoice $invoice): string
{
    config([
        'company.legal_name' => 'Ecoworking',
        'company.legal_form' => 'SARL',
        'company.siret' => '123 456 789 00012',
        'company.vat_number' => 'FR12345678901',
        'company.rcs' => 'Lyon 123 456 789',
        'company.address.line1' => '13 rue Général de Miribel',
        'company.address.postal_code' => '69007',
        'company.address.city' => 'Lyon',
    ]);

    return view('invoices.pdf', ['invoice' => $invoice->load('lines')])->render();
}

it('porte les mentions obligatoires : numéro, émetteur (SIRET/TVA), pénalités L441-10', function () {
    $invoice = Invoice::factory()->issued()->create([
        'number' => 'EW-2026-00042',
        'subtotal_ht' => 150, 'total_vat' => 30, 'total_ttc' => 180,
    ]);
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id, 'vat_rate' => 20,
        'line_total_ht' => 150, 'line_vat' => 30, 'line_total_ttc' => 180,
    ]);

    $html = renderInvoicePdfView($invoice);

    expect($html)
        ->toContain('FACTURE')
        ->toContain('EW-2026-00042')                       // numéro chronologique (art. 289)
        ->toContain($invoice->issued_at->format('d/m/Y'))  // date d'émission
        ->toContain('SIRET : 123 456 789 00012')          // identité émetteur
        ->toContain('TVA : FR12345678901')
        ->toContain('RCS Lyon 123 456 789')
        ->toContain('paiement à 14 jours')                 // conditions de règlement
        ->toContain('3 fois le taux d\'intérêt légal')     // pénalités de retard
        ->toContain('indemnité forfaitaire pour frais de recouvrement de 40 €')
        ->toContain('L441-10');
});

it('ventile la TVA par taux : base HT et montant de taxe par taux', function () {
    $invoice = Invoice::factory()->issued()->create([
        'number' => 'EW-2026-00043',
        'subtotal_ht' => 350, 'total_vat' => 41, 'total_ttc' => 391,
    ]);
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id, 'vat_rate' => 20,
        'line_total_ht' => 150, 'line_vat' => 30, 'line_total_ttc' => 180,
    ]);
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id, 'vat_rate' => 5.5,
        'line_total_ht' => 200, 'line_vat' => 11, 'line_total_ttc' => 211,
    ]);

    $html = renderInvoicePdfView($invoice);

    expect($html)
        ->toContain('TVA 20,0 % (base 150,00 €)')
        ->toContain('30,00 €')
        ->toContain('TVA 5,5 % (base 200,00 €)')
        ->toContain('11,00 €')
        ->toContain('391,00 €'); // total TTC
});

it('identifie un avoir et référence la facture annulée', function () {
    $cancelled = Invoice::factory()->issued()->create(['number' => 'EW-2026-00050']);
    $creditNote = Invoice::factory()->issued()->creditNote()->create([
        'number' => 'EW-2026-00051',
        'credit_note_for_invoice_id' => $cancelled->id,
    ]);

    $html = renderInvoicePdfView($creditNote);

    expect($html)
        ->toContain('AVOIR')
        ->toContain('EW-2026-00051')
        ->toContain('Annule la facture EW-2026-00050')
        ->toContain('annule et remplace la facture référencée');
});

<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Services\CancelInvoiceService;
use App\Services\InvoiceNumberingService;
use App\Services\IssueInvoiceService;

/**
 * C3.5 — Logique de facturation (Services, hors Resource). Couvre les règles
 * dures CLAUDE.md §3.6 : numérotation sans trou, montants figés à l'émission,
 * facture émise non supprimable, annulation = cancelled + avoir.
 */
function draftInvoiceWithLines(?Company $company = null): Invoice
{
    $company ??= Company::factory()->create([
        'legal_name' => 'Acme SCOP',
        'siret' => '12345678901234',
        'address_line1' => '10 rue du Lac',
        'city' => 'Lyon',
        'postal_code' => '69003',
    ]);

    $invoice = Invoice::factory()->create([
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'status' => InvoiceStatus::Draft->value,
        'number' => null,
    ]);

    InvoiceLine::factory()->for($invoice)->create([
        'description' => 'Abonnement résident',
        'quantity' => 1,
        'unit_price_ht' => 200.00,
        'vat_rate' => 20.00,
        'line_total_ht' => 200.00,
        'line_vat' => 40.00,
        'line_total_ttc' => 240.00,
    ]);
    InvoiceLine::factory()->for($invoice)->create([
        'description' => 'Tickets salle',
        'quantity' => 2,
        'unit_price_ht' => 25.00,
        'vat_rate' => 20.00,
        'line_total_ht' => 50.00,
        'line_vat' => 10.00,
        'line_total_ttc' => 60.00,
    ]);

    return $invoice->fresh();
}

it('numérote de façon chronologique sans trou', function () {
    $service = app(InvoiceNumberingService::class);

    expect($service->nextNumber(2026))->toBe('EW-2026-00001')
        ->and($service->nextNumber(2026))->toBe('EW-2026-00002')
        ->and($service->nextNumber(2027))->toBe('EW-2027-00001');
});

it('émet un brouillon : numéro posé, totaux figés, adresse snapshotée', function () {
    $invoice = draftInvoiceWithLines();

    app(IssueInvoiceService::class)->issue($invoice);
    $invoice->refresh();

    expect($invoice->number)->toBe('EW-'.now()->year.'-00001')
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and((float) $invoice->subtotal_ht)->toBe(250.00)
        ->and((float) $invoice->total_vat)->toBe(50.00)
        ->and((float) $invoice->total_ttc)->toBe(300.00)
        ->and($invoice->billing_name)->toBe('Acme SCOP')
        ->and($invoice->billing_siret)->toBe('12345678901234')
        ->and($invoice->billing_address['city'])->toBe('Lyon')
        ->and($invoice->issued_at->toDateString())->toBe(now()->toDateString())
        ->and($invoice->due_at->toDateString())->toBe(now()->addDays(14)->toDateString());
});

it('un brouillon ne consomme pas le compteur tant qu\'il n\'est pas émis', function () {
    draftInvoiceWithLines();
    $issued = draftInvoiceWithLines();

    app(IssueInvoiceService::class)->issue($issued);

    // Premier numéro malgré l'existence d'un autre brouillon non émis.
    expect($issued->refresh()->number)->toBe('EW-'.now()->year.'-00001');
});

it('refuse de réémettre une facture déjà émise', function () {
    $invoice = draftInvoiceWithLines();
    app(IssueInvoiceService::class)->issue($invoice);

    app(IssueInvoiceService::class)->issue($invoice->refresh());
})->throws(RuntimeException::class);

it('annule une facture émise en générant un avoir miroir lié', function () {
    $invoice = draftInvoiceWithLines();
    app(IssueInvoiceService::class)->issue($invoice);
    $invoice->refresh();

    $creditNote = app(CancelInvoiceService::class)->cancel($invoice, 'Erreur de montant');
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->cancelled_at)->not->toBeNull()
        ->and($invoice->cancellation_credit_note_id)->toBe($creditNote->id);

    expect($creditNote->is_credit_note)->toBeTrue()
        ->and($creditNote->credit_note_for_invoice_id)->toBe($invoice->id)
        ->and($creditNote->number)->toBe('EW-'.now()->year.'-00002')
        ->and((float) $creditNote->total_ttc)->toBe(-300.00)
        ->and($creditNote->lines)->toHaveCount(2)
        ->and((float) $creditNote->lines->sum('line_total_ttc'))->toBe(-300.00);
});

it('n\'émet qu\'un seul avoir même si l\'annulation est rejouée (review F2)', function () {
    $invoice = draftInvoiceWithLines();
    app(IssueInvoiceService::class)->issue($invoice);

    app(CancelInvoiceService::class)->cancel($invoice->refresh());

    expect(fn () => app(CancelInvoiceService::class)->cancel($invoice->refresh()))
        ->toThrow(RuntimeException::class, 'Facture déjà annulée.')
        ->and(Invoice::query()->where('is_credit_note', true)->count())->toBe(1);
});

it('refuse d\'annuler un brouillon (il se supprime)', function () {
    $invoice = draftInvoiceWithLines();

    app(CancelInvoiceService::class)->cancel($invoice);
})->throws(RuntimeException::class);

it('interdit la suppression d\'une facture émise (InvoicePolicy)', function () {
    $admin = User::factory()->admin()->create();
    $invoice = draftInvoiceWithLines();
    app(IssueInvoiceService::class)->issue($invoice);

    expect($admin->can('delete', $invoice->refresh()))->toBeFalse()
        ->and($admin->can('delete', draftInvoiceWithLines()))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceLineCalculator;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C3.5 — Resources facturation (Invoice, Payment). Rendu + actions émission/avoir
 * pilotées via la page d'édition (logique déléguée aux Services).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

dataset('billingPages', [
    'factures' => [ListInvoices::class, CreateInvoice::class, EditInvoice::class, fn () => Invoice::factory()->create()],
    'paiements' => [ListPayments::class, CreatePayment::class, EditPayment::class, fn () => Payment::factory()->create()],
]);

it('rend les pages list / create / edit pour un admin', function (string $list, string $create, string $edit, callable $make) {
    Livewire::test($list)->assertOk();
    Livewire::test($create)->assertOk();

    $record = $make();
    Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
})->with('billingPages');

it('calcule les totaux de ligne avec remise côté serveur', function () {
    $totals = InvoiceLineCalculator::totals([
        'quantity' => 2,
        'unit_price_ht' => 100,
        'discount_rate' => 10,
        'vat_rate' => 20,
    ]);

    expect($totals['line_total_ht'])->toBe('180.00')
        ->and($totals['line_vat'])->toBe('36.00')
        ->and($totals['line_total_ttc'])->toBe('216.00');
});

it('émet une facture via l\'action de la page d\'édition', function () {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Draft->value,
        'number' => null,
    ]);
    InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 1,
        'unit_price_ht' => 100,
        'vat_rate' => 20,
        'line_total_ht' => 100,
        'line_vat' => 20,
        'line_total_ttc' => 120,
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('issue')
        // Review #19 : le toast de succès part bien sans $action->success()
        // explicite (statut Success par défaut en Filament 5).
        ->assertNotified('Facture émise');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->number)->not->toBeNull();
});

it('consulte une facture émise en lecture seule (édition interdite, §3.6)', function () {
    $issued = Invoice::factory()->issued()->create();
    InvoiceLine::factory()->for($issued)->create([
        'quantity' => 1,
        'unit_price_ht' => 100,
        'vat_rate' => 20,
        'line_total_ht' => 100,
        'line_vat' => 20,
        'line_total_ttc' => 120,
    ]);

    // Émise → la vue figée se rend ; l'édition est refusée par la Policy.
    Livewire::test(ViewInvoice::class, ['record' => $issued->getRouteKey()])->assertOk();
    Livewire::test(EditInvoice::class, ['record' => $issued->getRouteKey()])->assertForbidden();
});

it('annule une facture émise via l\'action de la page de consultation', function () {
    $invoice = Invoice::factory()->issued()->create();
    InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 1,
        'unit_price_ht' => 100,
        'vat_rate' => 20,
        'line_total_ht' => 100,
        'line_vat' => 20,
        'line_total_ttc' => 120,
    ]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Erreur de saisie'])
        ->assertNotified('Facture annulée, avoir généré');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->cancellation_credit_note_id)->not->toBeNull();
});

it('refuse un montant de paiement nul ou négatif (review F11)', function () {
    $invoice = Invoice::factory()->issued()->create(['total_ttc' => 100]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Transfer->value,
            'amount' => -50,
            'paid_at' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['amount']);

    expect(Payment::query()->count())->toBe(0);
});

it('réserve les paiements à l\'admin (PaymentPolicy)', function () {
    // InvoicePolicy::viewAny est ouvert (contacts facturation, portail C5) ; le
    // back-office reste protégé par canAccessPanel (cf. FilamentPanelTest).
    // Les paiements, eux, sont strictement admin-only même au niveau policy.
    actingAs(User::factory()->member()->create());

    Livewire::test(ListPayments::class)->assertForbidden();
});

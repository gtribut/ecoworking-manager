<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\InvoicePdfService;
use App\Services\IssueInvoiceService;
use App\Services\MonthlyBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** C6 — Facturation : génération PDF (C6.2), paiements (C6.6), idempotence récurrente (C6.5). */

// --- C6.2 Génération PDF -------------------------------------------------

it('génère et stocke le PDF d\'une facture émise', function () {
    Storage::fake();
    $invoice = Invoice::factory()->issued()->create(['number' => 'EW-2026-00042']);
    InvoiceLine::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    $path = app(InvoicePdfService::class)->generate($invoice);

    expect($path)->toBe('invoices/2026/EW-2026-00042.pdf')
        ->and(Storage::exists($path))->toBeTrue()
        ->and($invoice->fresh()->pdf_path)->toBe($path)
        ->and(Storage::get($path))->toStartWith('%PDF');
});

it('programme la génération du PDF à l\'émission', function () {
    Queue::fake();
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft->value, 'number' => null]);
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id,
        'line_total_ht' => 100, 'line_vat' => 20, 'line_total_ttc' => 120,
    ]);

    app(IssueInvoiceService::class)->issue($invoice);

    Queue::assertPushed(GenerateInvoicePdfJob::class);
});

// --- C6.6 Paiements & statut ---------------------------------------------

it('recalcule amount_paid et le statut au fil des paiements', function () {
    $invoice = Invoice::factory()->issued()->create(['total_ttc' => 100, 'amount_paid' => 0]);

    Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 40]);
    expect($invoice->fresh()->amount_paid)->toBe('40.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 60]);
    expect($invoice->fresh()->amount_paid)->toBe('100.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('recalcule amount_paid à la suppression d\'un paiement', function () {
    $invoice = Invoice::factory()->issued()->create(['total_ttc' => 100, 'amount_paid' => 0]);
    $payment = Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 100]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $payment->delete();

    expect($invoice->fresh()->amount_paid)->toBe('0.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('passe en retard les factures émises échues et non soldées', function () {
    $overdue = Invoice::factory()->issued()->create([
        'due_at' => now()->subDays(3)->toDateString(),
        'total_ttc' => 100, 'amount_paid' => 0, 'status' => InvoiceStatus::Sent->value,
    ]);
    $current = Invoice::factory()->issued()->create([
        'due_at' => now()->addDays(10)->toDateString(),
        'total_ttc' => 100, 'amount_paid' => 0, 'status' => InvoiceStatus::Sent->value,
    ]);

    $this->artisan('invoices:update-overdue')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($current->fresh()->status)->toBe(InvoiceStatus::Sent);
});

// --- C6.5 Facturation récurrente idempotente -----------------------------

it('génère un brouillon mensuel et reste idempotent (pas de doublon)', function () {
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 200, 'vat_rate' => 20]);
    $subscription = Subscription::factory()->create([
        'offer_id' => $offer->id,
        'starts_at' => '2026-04-01',
    ]);
    $svc = app(MonthlyBillingService::class);
    $start = CarbonImmutable::parse('2026-04-01');
    $end = CarbonImmutable::parse('2026-04-30');

    $invoice = $svc->generateForSubscription($subscription, $start, $end);
    expect($invoice)->not->toBeNull()
        ->and($invoice->subtotal_ht)->toBe('200.00');

    // Second appel : aucune nouvelle facture (idempotent).
    expect($svc->generateForSubscription($subscription, $start, $end))->toBeNull()
        ->and(InvoiceLine::where('related_id', $subscription->id)
            ->where('related_type', $subscription->getMorphClass())->count())->toBe(1);
});

it('proratise au nombre de jours consommés (bornes incluses)', function () {
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 300, 'vat_rate' => 20]);
    $subscription = Subscription::factory()->create([
        'offer_id' => $offer->id,
        'starts_at' => '2026-04-16', // 16→30 avril = 15 j sur 30
    ]);

    $invoice = app(MonthlyBillingService::class)->generateForSubscription(
        $subscription,
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
    );

    expect($invoice->subtotal_ht)->toBe('150.00'); // 300 × 15/30
});

it('applique la remise négociée de l\'entité facturée', function () {
    $company = Company::factory()->withDiscount(10.0)->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 100, 'vat_rate' => 20]);
    $subscription = Subscription::factory()->create([
        'offer_id' => $offer->id,
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);

    $invoice = app(MonthlyBillingService::class)->generateForSubscription(
        $subscription,
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
    );

    expect($invoice->subtotal_ht)->toBe('90.00'); // 100 − 10 %
});

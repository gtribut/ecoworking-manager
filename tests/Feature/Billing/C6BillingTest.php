<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceLineSubscription;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\CancelInvoiceService;
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

it('programme la génération du PDF de l\'avoir à l\'annulation (review F3)', function () {
    Queue::fake();
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft->value, 'number' => null]);
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id,
        'line_total_ht' => 100, 'line_vat' => 20, 'line_total_ttc' => 120,
    ]);
    app(IssueInvoiceService::class)->issue($invoice);

    $creditNote = app(CancelInvoiceService::class)->cancel($invoice->refresh());

    // L'avoir est une pièce comptable : son PDF doit exister comme celui de la facture.
    Queue::assertPushed(
        GenerateInvoicePdfJob::class,
        fn (GenerateInvoicePdfJob $job): bool => $job->invoiceId === $creditNote->id,
    );
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

// --- C6.5 Facturation récurrente par entité, regroupée & idempotente ------

it('produit UNE facture par entité, lignes regroupées par prestation (× qté)', function () {
    $company = Company::factory()->create();
    $deskOffer = Offer::factory()->subscription()->create(['unit_price_ht' => 328.50, 'vat_rate' => 20]);
    $personOffer = Offer::factory()->subscription()->create(['unit_price_ht' => 59.00, 'vat_rate' => 20]);

    // 10 bureaux résident + 3 personnes additionnelles, tous plein mois.
    Subscription::factory()->count(10)->create([
        'offer_id' => $deskOffer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);
    Subscription::factory()->count(3)->create([
        'offer_id' => $personOffer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);

    $invoice = app(MonthlyBillingService::class)->generateForEntity(
        'company', $company->id,
        CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'),
    );

    expect($invoice)->not->toBeNull()
        ->and($invoice->billable_type)->toBe('company')
        ->and($invoice->lines)->toHaveCount(2); // 1 ligne / prestation, pas 13

    $deskLine = $invoice->lines->firstWhere('unit_price_ht', '328.50');
    expect($deskLine->quantity)->toBe('10.00')
        ->and($deskLine->related_type)->toBeNull()         // ligne regroupée
        ->and($deskLine->line_total_ht)->toBe('3285.00')
        ->and($deskLine->subscriptionLinks()->count())->toBe(10) // traçabilité
        ->and($invoice->subtotal_ht)->toBe('3462.00');     // 3285 + 177
});

it('met un prorata sur une ligne séparée (pas fondu dans le plein mois)', function () {
    $company = Company::factory()->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 328.50, 'vat_rate' => 20]);

    Subscription::factory()->count(10)->create([
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);
    Subscription::factory()->create([ // démarré le 15 → prorata 16/30 j
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-15',
    ]);

    $invoice = app(MonthlyBillingService::class)->generateForEntity(
        'company', $company->id,
        CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'),
    );

    expect($invoice->lines)->toHaveCount(2);
    $prorata = $invoice->lines->firstWhere('quantity', '1.00');
    expect($prorata->unit_price_ht)->toBe('175.20')      // 328.50 × 16/30
        ->and($prorata->description)->toContain('prorata 16/30 j');
});

it('reste idempotent au grain entité (pas de doublon de facture)', function () {
    $company = Company::factory()->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 200, 'vat_rate' => 20]);
    Subscription::factory()->count(2)->create([
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);
    $svc = app(MonthlyBillingService::class);
    $start = CarbonImmutable::parse('2026-04-01');
    $end = CarbonImmutable::parse('2026-04-30');

    expect($svc->generateForEntity('company', $company->id, $start, $end))->not->toBeNull();
    // Second passage : aucune nouvelle facture.
    expect($svc->generateForEntity('company', $company->id, $start, $end))->toBeNull()
        ->and(Invoice::where('billable_id', $company->id)->where('billable_type', 'company')->count())->toBe(1);
});

it('refacture l\'entité après suppression du brouillon récurrent (review F4)', function () {
    $company = Company::factory()->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 200, 'vat_rate' => 20]);
    Subscription::factory()->create([
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);
    $svc = app(MonthlyBillingService::class);
    $start = CarbonImmutable::parse('2026-04-01');
    $end = CarbonImmutable::parse('2026-04-30');

    $draft = $svc->generateForEntity('company', $company->id, $start, $end);
    expect($draft)->not->toBeNull();

    // Suppression du brouillon (soft delete) : les liaisons d'idempotence
    // doivent être purgées, sinon l'entité devient infacturable sur le mois.
    $draft->delete();

    expect(InvoiceLineSubscription::query()->count())->toBe(0)
        ->and($svc->generateForEntity('company', $company->id, $start, $end))->not->toBeNull();
});

it('facture le reliquat des abonnements non encore facturés sur la période (review F5)', function () {
    $company = Company::factory()->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 300, 'vat_rate' => 20]);
    $svc = app(MonthlyBillingService::class);
    $start = CarbonImmutable::parse('2026-04-01');
    $end = CarbonImmutable::parse('2026-04-30');

    // Abo A facturé « instant T » (démarrage en cours de mois déjà traité).
    $subA = Subscription::factory()->create([
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
    ]);
    expect($svc->generateForSubscription($subA, $start, $end))->not->toBeNull();

    // Abo B souscrit ensuite : la génération d'entité doit facturer B seul
    // (et non ignorer toute l'entité parce que A est déjà facturé).
    $subB = Subscription::factory()->create([
        'offer_id' => $offer->id, 'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-15',
    ]);
    $invoice = $svc->generateForEntity('company', $company->id, $start, $end);

    expect($invoice)->not->toBeNull()
        ->and($invoice->lines)->toHaveCount(1) // prorata de B uniquement
        ->and($invoice->lines->first()->subscriptionLinks()->pluck('subscription_id')->all())->toBe([$subB->id]);

    // A n'est pas refacturé, et l'idempotence tient toujours au passage suivant.
    expect(InvoiceLineSubscription::query()->where('subscription_id', $subA->id)->count())->toBe(1)
        ->and($svc->generateForEntity('company', $company->id, $start, $end))->toBeNull();
});

it('génère une facture par entité distincte via generateMonth', function () {
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 100, 'vat_rate' => 20]);
    Subscription::factory()->count(2)->create([
        'offer_id' => $offer->id, 'billable_type' => 'company',
        'billable_id' => Company::factory()->create()->id, 'starts_at' => '2026-04-01',
    ]);
    Subscription::factory()->create([
        'offer_id' => $offer->id, 'billable_type' => 'company',
        'billable_id' => Company::factory()->create()->id, 'starts_at' => '2026-04-01',
    ]);

    $created = app(MonthlyBillingService::class)->generateMonth(CarbonImmutable::parse('2026-04-15'));

    expect($created)->toHaveCount(2); // 2 entités → 2 factures
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

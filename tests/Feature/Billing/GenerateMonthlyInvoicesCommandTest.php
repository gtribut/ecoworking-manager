<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Subscription;

/**
 * C6.5 — point d'entrée cron `invoices:generate-monthly` (review 06 M3).
 * Le service MonthlyBillingService est testé par ailleurs (C6BillingTest) :
 * ici on verrouille le câblage artisan (défaut, --month, code retour, sortie).
 */
it('génère les brouillons du mois courant par défaut et sort en succès', function () {
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 100, 'vat_rate' => 20]);
    Subscription::factory()->create([
        'offer_id' => $offer->id,
        'starts_at' => now()->startOfMonth()->toDateString(),
    ]);

    $this->artisan('invoices:generate-monthly')
        ->expectsOutputToContain(sprintf(
            '1 brouillon(s) de facture généré(s) pour %s.',
            now()->format('Y-m'),
        ))
        ->assertSuccessful();

    $invoice = Invoice::sole();
    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->number)->toBeNull(); // brouillon : compteur non consommé (§3.6)
});

it('cible le mois passé en option --month=YYYY-MM', function () {
    $company = Company::factory()->create();
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 300, 'vat_rate' => 20]);
    Subscription::factory()->create([
        'offer_id' => $offer->id,
        'billable_type' => 'company', 'billable_id' => $company->id,
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-04-30', // hors période pour tout autre mois
    ]);

    $this->artisan('invoices:generate-monthly', ['--month' => '2026-04'])
        ->expectsOutputToContain('1 brouillon(s) de facture généré(s) pour 2026-04.')
        ->assertSuccessful();

    $invoice = Invoice::sole();
    expect($invoice->billable_id)->toBe($company->id)
        ->and($invoice->lines->first()->period_start->toDateString())->toBe('2026-04-01')
        ->and($invoice->lines->first()->period_end->toDateString())->toBe('2026-04-30');
});

it('sort en succès avec un décompte à zéro quand rien n\'est à facturer', function () {
    $this->artisan('invoices:generate-monthly')
        ->expectsOutputToContain('0 brouillon(s) de facture généré(s)')
        ->assertExitCode(0);

    expect(Invoice::count())->toBe(0);
});

it('reste idempotent en relance via la commande (aucun doublon)', function () {
    $offer = Offer::factory()->subscription()->create(['unit_price_ht' => 100, 'vat_rate' => 20]);
    Subscription::factory()->create([
        'offer_id' => $offer->id,
        'starts_at' => now()->startOfMonth()->toDateString(),
    ]);

    $this->artisan('invoices:generate-monthly')->assertSuccessful();
    $this->artisan('invoices:generate-monthly')
        ->expectsOutputToContain('0 brouillon(s)')
        ->assertSuccessful();

    expect(Invoice::count())->toBe(1);
});

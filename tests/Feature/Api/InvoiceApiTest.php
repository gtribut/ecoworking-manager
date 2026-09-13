<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

/** C4.3 — Endpoints factures (liste scopée + PDF). Isolation = InvoicePolicy (C2.4). */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions (view-billing-section)
});

/** Crée un contact facturation rattachant $user à $company. */
function apiBillingContactFor(Company $company): User
{
    $user = User::factory()->billingContact()->create();
    Contact::factory()->billing()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
    ]);

    return $user;
}

it('protège les factures : 401 si non authentifié', function () {
    $this->getJson('/api/invoices')->assertUnauthorized();
});

it('liste les factures émises de l\'entité du contact facturation, jamais les brouillons', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);

    $issued = Invoice::factory()->issued()->create(['billable_type' => 'company', 'billable_id' => $company->id]);
    Invoice::factory()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
        'status' => 'draft', 'number' => null,
    ]);

    $response = $this->actingAs($user)->getJson('/api/invoices')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($issued->id)
        ->and($ids)->toHaveCount(1); // le brouillon est exclu
});

it('isole les factures entre entités (un contact ne voit pas une autre entité)', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = apiBillingContactFor($companyA);

    Invoice::factory()->issued()->create(['billable_type' => 'company', 'billable_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/invoices')->assertOk();

    expect($response->json('data'))->toHaveCount(0);
});

it('refuse le module factures (403) à un membre sans rôle billing_contact', function () {
    // Lot B (PRD §2.5/§3.6.1) : module masqué de la nav ET refusé côté API —
    // une liste vide laissait croire à une absence de factures.
    $company = Company::factory()->create();
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
    Invoice::factory()->issued()->create(['billable_type' => 'company', 'billable_id' => $company->id]);

    $this->actingAs($user)->getJson('/api/invoices')->assertForbidden();
});

it('télécharge le PDF d\'une facture du périmètre', function () {
    Storage::fake();
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
        'pdf_path' => 'invoices/2026/EW-2026-00001.pdf',
    ]);
    Storage::put($invoice->pdf_path, '%PDF-1.4 fake');

    $this->actingAs($user)
        ->get("/api/invoices/{$invoice->id}/pdf")
        ->assertOk()
        ->assertDownload($invoice->number.'.pdf');
});

it('renvoie 404 si le PDF n\'est pas encore généré', function () {
    Storage::fake();
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
        'pdf_path' => null,
    ]);

    $this->actingAs($user)
        ->getJson("/api/invoices/{$invoice->id}/pdf")
        ->assertNotFound();
});

it('refuse le téléchargement du PDF d\'une autre entité (403)', function () {
    Storage::fake();
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = apiBillingContactFor($companyA);
    $invoiceB = Invoice::factory()->issued()->create([
        'billable_type' => 'company', 'billable_id' => $companyB->id,
        'pdf_path' => 'invoices/2026/EW-2026-09999.pdf',
    ]);
    Storage::put($invoiceB->pdf_path, '%PDF fake');

    $this->actingAs($userA)
        ->getJson("/api/invoices/{$invoiceB->id}/pdf")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Lot D — tri, filtres et recherche (PRD §3.6.2)
|--------------------------------------------------------------------------
|
| `issued_at` est une colonne DATE (pas timestamptz) : les filtres mois/année
| se comparent donc côté SQL (whereYear/whereMonth) sans passer par un Carbon
| PHP — aucun décalage de fuseau possible (piège connu du projet).
|
*/

/** Facture émise d'une entité, à une date et un statut donnés. */
function apiIssuedInvoiceFor(Company $company, string $number, string $issuedAt, string $status = 'sent'): Invoice
{
    return Invoice::factory()->issued()->create([
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'number' => $number,
        'issued_at' => $issuedAt,
        'status' => $status,
    ]);
}

it('filtre les factures par année (comparaison SQL sur issued_at)', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2025-00001', '2025-12-31');
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-01');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?year=2026')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00001']));
});

it('filtre les factures par mois combiné à l\'année', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-15');
    apiIssuedInvoiceFor($company, 'EW-2026-00002', '2026-02-15');
    apiIssuedInvoiceFor($company, 'EW-2025-00009', '2025-02-15');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?year=2026&month=2')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00002']));
});

it('refuse un filtre mois sans année (422)', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);

    $this->actingAs($user)->getJson('/api/invoices?month=2')
        ->assertStatus(422)
        ->assertJsonValidationErrors('year');
});

it('filtre les factures par statut', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-15', 'paid');
    apiIssuedInvoiceFor($company, 'EW-2026-00002', '2026-01-16', 'overdue');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?status=overdue')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00002']));
});

it('refuse le statut brouillon comme filtre (422) — jamais visible du membre', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);

    $this->actingAs($user)->getJson('/api/invoices?status=draft')
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('recherche une facture par numéro, sans casse et en fragment', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00042', '2026-03-01');
    apiIssuedInvoiceFor($company, 'EW-2026-00099', '2026-03-02');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?q=00042')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00042']));

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?q=ew-2026')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toHaveCount(2);
});

it('échappe les jokers LIKE saisis dans la recherche', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00042', '2026-03-01');

    // `%` saisi par l'utilisateur doit être littéral, pas un joker.
    $response = $this->actingAs($user)->getJson('/api/invoices?q=%25')->assertOk();

    expect($response->json('data'))->toHaveCount(0);
});

it('trie par numéro ascendant sur demande', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00002', '2026-01-05');
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-02-05');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices?sort=number&direction=asc')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00001', 'EW-2026-00002']));
});

it('trie par date décroissante par défaut', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-05');
    apiIssuedInvoiceFor($company, 'EW-2026-00002', '2026-02-05');

    $numbers = collect(
        $this->actingAs($user)->getJson('/api/invoices')->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00002', 'EW-2026-00001']));
});

it('refuse un tri ou une direction inconnus (422)', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);

    $this->actingAs($user)->getJson('/api/invoices?sort=total_ttc')
        ->assertStatus(422)->assertJsonValidationErrors('sort');

    $this->actingAs($user)->getJson('/api/invoices?direction=up')
        ->assertStatus(422)->assertJsonValidationErrors('direction');
});

it('refuse une année ou un mois hors bornes et une pagination hors bornes (422)', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);

    $this->actingAs($user)->getJson('/api/invoices?year=1999')
        ->assertStatus(422)->assertJsonValidationErrors('year');
    $this->actingAs($user)->getJson('/api/invoices?year=2026&month=13')
        ->assertStatus(422)->assertJsonValidationErrors('month');
    $this->actingAs($user)->getJson('/api/invoices?per_page=500')
        ->assertStatus(422)->assertJsonValidationErrors('per_page');
});

it('combine filtres, recherche et tri sans élargir le périmètre', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = apiBillingContactFor($companyA);

    apiIssuedInvoiceFor($companyA, 'EW-2026-00001', '2026-01-10', 'paid');
    apiIssuedInvoiceFor($companyA, 'EW-2026-00002', '2026-01-20', 'paid');
    apiIssuedInvoiceFor($companyA, 'EW-2026-00003', '2026-02-20', 'paid');
    // Même numéro-fragment, même mois, même statut, mais autre entité.
    apiIssuedInvoiceFor($companyB, 'EW-2026-00004', '2026-01-15', 'paid');

    $numbers = collect(
        $this->actingAs($userA)
            ->getJson('/api/invoices?year=2026&month=1&status=paid&q=EW-2026&sort=number&direction=asc')
            ->assertOk()->json('data')
    )->pluck('number');

    expect($numbers)->toEqual(collect(['EW-2026-00001', 'EW-2026-00002']));
});

it('n\'élargit jamais le périmètre : aucun filtre ne fait apparaître une facture d\'une autre entité', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = apiBillingContactFor($companyA);
    apiIssuedInvoiceFor($companyB, 'EW-2026-09999', '2026-04-01', 'paid');

    foreach ([
        '?year=2026',
        '?year=2026&month=4',
        '?status=paid',
        '?q=09999',
        '?sort=number&direction=asc',
        '?per_page=100',
    ] as $queryString) {
        $data = $this->actingAs($userA)->getJson('/api/invoices'.$queryString)->assertOk()->json('data');
        expect($data)->toHaveCount(0, "périmètre élargi par {$queryString}");
    }
});

it('n\'expose jamais un brouillon, même avec des filtres qui le cibleraient', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    Invoice::factory()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
        'status' => 'draft', 'number' => null, 'issued_at' => '2026-05-01',
    ]);

    $data = $this->actingAs($user)->getJson('/api/invoices?year=2026&month=5')->assertOk()->json('data');

    expect($data)->toHaveCount(0);
});

it('respecte per_page dans la pagination', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-05');
    apiIssuedInvoiceFor($company, 'EW-2026-00002', '2026-01-06');
    apiIssuedInvoiceFor($company, 'EW-2026-00003', '2026-01-07');

    $response = $this->actingAs($user)->getJson('/api/invoices?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.last_page'))->toBe(2);
});

it('tolère des paramètres vides envoyés par la SPA (selects « Tous »)', function () {
    $company = Company::factory()->create();
    $user = apiBillingContactFor($company);
    apiIssuedInvoiceFor($company, 'EW-2026-00001', '2026-01-05');

    // ConvertEmptyStringsToNull transforme `?status=` en null : le filtre est
    // simplement inactif, pas une erreur de validation.
    $response = $this->actingAs($user)
        ->getJson('/api/invoices?year=2026&status=&month=&q=&sort=&direction=&per_page=')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

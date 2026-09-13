<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/** C4.3 — Endpoints factures (liste scopée + PDF). Isolation = InvoicePolicy (C2.4). */

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

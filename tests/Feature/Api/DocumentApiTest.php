<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Models\AdministrativeDocument;
use App\Models\Company;
use App\Models\Contact;
use App\Models\InternalDocument;
use App\Models\MemberDocumentValidation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions (validate-internal-document)
});

/**
 * C12.4 — Documents côté portail : internes à valider (audience + validation
 * append-only, MemberDocumentValidationPolicy exercée) et administratifs
 * d'entité (périmètre billing_contact, miroir InvoiceApiTest).
 */

/** Crée un contact facturation rattachant un nouveau user à $company. */
function docBillingContactFor(Company $company): User
{
    $user = User::factory()->billingContact()->create();
    Contact::factory()->billing()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
    ]);

    return $user;
}

// ---------------------------------------------------------------------------
// Authentification
// ---------------------------------------------------------------------------

it('protège les documents : 401 si non authentifié', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/documents/internal'],
    ['GET', '/api/documents/internal/1/pdf'],
    ['POST', '/api/documents/internal/1/validation'],
    ['GET', '/api/documents/administrative'],
    ['GET', '/api/documents/administrative/1/pdf'],
]);

// ---------------------------------------------------------------------------
// Documents internes — applicabilité (audience, actif, publié)
// ---------------------------------------------------------------------------

it('liste les documents internes applicables au membre selon son audience', function () {
    $resident = User::factory()->resident()->create();

    $all = InternalDocument::factory()->create(['audience' => Audience::All->value]);
    $residents = InternalDocument::factory()->create(['audience' => Audience::Residents->value]);
    InternalDocument::factory()->create(['audience' => Audience::Additional->value]);

    $response = $this->actingAs($resident)->getJson('/api/documents/internal')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($all->id)
        ->and($ids)->toContain($residents->id)
        ->and($ids)->toHaveCount(2); // le doc « additional » est hors audience
});

it('exclut les documents inactifs ou non publiés', function () {
    $member = User::factory()->resident()->create();

    InternalDocument::factory()->create(['is_active' => false]);
    InternalDocument::factory()->create(['published_at' => null]);
    InternalDocument::factory()->create(['published_at' => now()->addDay()]);
    $visible = InternalDocument::factory()->create();

    $response = $this->actingAs($member)->getJson('/api/documents/internal')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$visible->id]);
});

it('marque le statut de validation pour la version courante uniquement', function () {
    $member = User::factory()->resident()->create();
    $validated = InternalDocument::factory()->create(['version' => '1.0']);
    $outdated = InternalDocument::factory()->cgu()->create(['version' => '2.0']);

    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $validated->id,
        'user_id' => $member->id,
        'version' => '1.0',
    ]);
    // Validation d'une ANCIENNE version → re-validation requise (PRD §3.3.2).
    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $outdated->id,
        'user_id' => $member->id,
        'version' => '1.0',
    ]);

    $response = $this->actingAs($member)->getJson('/api/documents/internal')->assertOk();

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId[$validated->id]['is_validated'])->toBeTrue()
        ->and($byId[$validated->id]['validated_at'])->not->toBeNull()
        ->and($byId[$outdated->id]['is_validated'])->toBeFalse()
        ->and($byId[$outdated->id]['validated_at'])->toBeNull();
});

it('ne montre jamais la validation d\'un autre membre (isolation A/B)', function () {
    $memberA = User::factory()->resident()->create();
    $memberB = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['version' => '1.0']);

    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $document->id,
        'user_id' => $memberA->id,
        'version' => '1.0',
    ]);

    $response = $this->actingAs($memberB)->getJson('/api/documents/internal')->assertOk();

    expect($response->json('data.0.is_validated'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Documents internes — validation
// ---------------------------------------------------------------------------

it('valide un document interne (version courante snapshotée, IP tracée)', function () {
    $member = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['version' => '3.1']);

    $this->actingAs($member)
        ->postJson("/api/documents/internal/{$document->id}/validation")
        ->assertCreated();

    $this->assertDatabaseHas('member_document_validations', [
        'internal_document_id' => $document->id,
        'user_id' => $member->id,
        'version' => '3.1',
    ]);
    expect(MemberDocumentValidation::query()->firstOrFail()->ip_address)->not->toBeNull();
});

it('refuse la double validation de la même version (422, append-only préservé)', function () {
    $member = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['version' => '1.0']);

    $this->actingAs($member)->postJson("/api/documents/internal/{$document->id}/validation")->assertCreated();
    $this->actingAs($member)->postJson("/api/documents/internal/{$document->id}/validation")->assertUnprocessable();

    expect(MemberDocumentValidation::query()->count())->toBe(1);
});

it('permet de re-valider après un changement de version (nouvelle ligne, historique conservé)', function () {
    $member = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['version' => '1.0']);

    $this->actingAs($member)->postJson("/api/documents/internal/{$document->id}/validation")->assertCreated();

    $document->update(['version' => '2.0']);

    $this->actingAs($member)->postJson("/api/documents/internal/{$document->id}/validation")->assertCreated();

    expect(MemberDocumentValidation::query()->where('user_id', $member->id)->pluck('version')->all())
        ->toBe(['1.0', '2.0']);
});

it('refuse la validation d\'un document hors audience (403)', function () {
    $additional = User::factory()->additional()->create();
    $document = InternalDocument::factory()->create(['audience' => Audience::Residents->value]);

    $this->actingAs($additional)
        ->postJson("/api/documents/internal/{$document->id}/validation")
        ->assertForbidden();

    expect(MemberDocumentValidation::query()->count())->toBe(0);
});

it('refuse la validation d\'un document inactif ou non publié (403)', function () {
    $member = User::factory()->resident()->create();
    $inactive = InternalDocument::factory()->create(['is_active' => false]);

    $this->actingAs($member)
        ->postJson("/api/documents/internal/{$inactive->id}/validation")
        ->assertForbidden();
});

it('refuse la validation à un billing_contact pur (Policy exercée, permission absente)', function () {
    $billingOnly = User::factory()->billingContact()->create();
    $document = InternalDocument::factory()->create(['audience' => Audience::All->value]);

    $this->actingAs($billingOnly)
        ->postJson("/api/documents/internal/{$document->id}/validation")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Documents internes — téléchargement PDF (disque privé + Gate)
// ---------------------------------------------------------------------------

it('télécharge le PDF d\'un document interne applicable', function () {
    Storage::fake();
    $member = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create([
        'title' => 'Charte interne',
        'version' => '1.0',
        'pdf_path' => 'internal-documents/charte.pdf',
    ]);
    Storage::put($document->pdf_path, '%PDF-1.4 fake');

    $this->actingAs($member)
        ->get("/api/documents/internal/{$document->id}/pdf")
        ->assertOk()
        ->assertDownload('charte-interne-v10.pdf');
});

it('refuse le PDF d\'un document interne hors audience (403)', function () {
    Storage::fake();
    $additional = User::factory()->additional()->create();
    $document = InternalDocument::factory()->create([
        'audience' => Audience::Residents->value,
        'pdf_path' => 'internal-documents/charte.pdf',
    ]);
    Storage::put($document->pdf_path, '%PDF fake');

    $this->actingAs($additional)
        ->getJson("/api/documents/internal/{$document->id}/pdf")
        ->assertForbidden();
});

it('renvoie 404 si le document interne n\'a pas de PDF', function () {
    Storage::fake();
    $member = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['pdf_path' => null]);

    $this->actingAs($member)
        ->getJson("/api/documents/internal/{$document->id}/pdf")
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Documents administratifs — périmètre billing (miroir factures)
// ---------------------------------------------------------------------------

it('liste les documents administratifs des entités du contact facturation', function () {
    $company = Company::factory()->create();
    $user = docBillingContactFor($company);
    $document = AdministrativeDocument::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->getJson('/api/documents/administrative')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$document->id]);
});

it('isole les documents administratifs entre entités (un contact ne voit pas une autre entité)', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = docBillingContactFor($companyA);

    AdministrativeDocument::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/documents/administrative')->assertOk();

    expect($response->json('data'))->toHaveCount(0);
});

it('masque les documents administratifs à un membre sans rôle billing_contact', function () {
    $company = Company::factory()->create();
    $user = User::factory()->resident()->create();
    AdministrativeDocument::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->getJson('/api/documents/administrative')->assertOk();

    expect($response->json('data'))->toHaveCount(0);
});

it('télécharge le PDF d\'un document administratif du périmètre', function () {
    Storage::fake();
    $company = Company::factory()->create();
    $user = docBillingContactFor($company);
    $document = AdministrativeDocument::factory()->create([
        'company_id' => $company->id,
        'title' => 'Contrat de domiciliation',
        'pdf_path' => 'administrative-documents/contrat.pdf',
    ]);
    Storage::put($document->pdf_path, '%PDF-1.4 fake');

    $this->actingAs($user)
        ->get("/api/documents/administrative/{$document->id}/pdf")
        ->assertOk()
        ->assertDownload('contrat-de-domiciliation.pdf');
});

it('refuse le PDF administratif d\'une autre entité (403)', function () {
    Storage::fake();
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = docBillingContactFor($companyA);
    $documentB = AdministrativeDocument::factory()->create([
        'company_id' => $companyB->id,
        'pdf_path' => 'administrative-documents/foreign.pdf',
    ]);
    Storage::put($documentB->pdf_path, '%PDF fake');

    $this->actingAs($userA)
        ->getJson("/api/documents/administrative/{$documentB->id}/pdf")
        ->assertForbidden();
});

it('refuse le PDF administratif à un membre de l\'entité sans rôle billing_contact (403)', function () {
    Storage::fake();
    $company = Company::factory()->create();
    $member = User::factory()->resident()->create();
    $document = AdministrativeDocument::factory()->create([
        'company_id' => $company->id,
        'pdf_path' => 'administrative-documents/contrat.pdf',
    ]);
    Storage::put($document->pdf_path, '%PDF fake');

    $this->actingAs($member)
        ->getJson("/api/documents/administrative/{$document->id}/pdf")
        ->assertForbidden();
});

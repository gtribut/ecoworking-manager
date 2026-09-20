<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Lot D — `GET /api/billing/entity` : bloc « Mon entreprise » / « Mes données de
 * facturation » du module administratif (PRD §3.6.4, §3.4.3).
 *
 * Périmètre = `linkedCompanyIds()` (entités dont l'utilisateur est contact de
 * facturation), pas `memberProfile->company` : un `billing_contact` pur n'a pas
 * de profil membre et peut couvrir plusieurs entités. La facturation « en nom
 * propre » n'est pas un cas à part : le particulier est le `billing_contact`
 * d'une entité perso (`companies.entity_type = individual`, PRD §3.3.2 acté).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** Rattache $user comme contact de facturation de $company. */
function billingContactOf(User $user, Company $company): void
{
    Contact::factory()->billing()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
    ]);
}

it('protège le bloc entité : 401 si non authentifié', function () {
    $this->getJson('/api/billing/entity')->assertUnauthorized();
});

it('renvoie toutes les entités facturables d\'un contact facturation pur (multi-entités)', function () {
    $user = User::factory()->billingContact()->create();
    $first = Company::factory()->create(['legal_name' => 'Alpha SAS']);
    $second = Company::factory()->create(['legal_name' => 'Beta SARL']);
    billingContactOf($user, $first);
    billingContactOf($user, $second);
    // Entité tierce : jamais visible.
    Company::factory()->create(['legal_name' => 'Gamma SA']);

    $names = collect(
        $this->actingAs($user)->getJson('/api/billing/entity')->assertOk()->json('data')
    )->pluck('legal_name')->sort()->values();

    expect($names)->toEqual(collect(['Alpha SAS', 'Beta SARL']));
});

it('expose les données de facturation complètes de l\'entité (PRD §3.6.4)', function () {
    $user = User::factory()->billingContact()->create();
    $company = Company::factory()->create([
        'legal_name' => 'Alpha SAS',
        'legal_form' => 'SAS',
        'siret' => '12345678901234',
        'vat_number' => 'FR12345678901',
        'billing_email' => 'factu@alpha.test',
        'address_line1' => '12 rue de la Part-Dieu',
        'address_line2' => 'Bâtiment B',
        'postal_code' => '69003',
        'city' => 'Lyon',
        'country' => 'FR',
        'preferred_payment_method' => 'sepa',
        'sepa_iban_last4' => '1234',
    ]);
    billingContactOf($user, $company);

    $this->actingAs($user)->getJson('/api/billing/entity')->assertOk()
        ->assertJsonPath('data.0.entity_type', 'company')
        ->assertJsonPath('data.0.legal_name', 'Alpha SAS')
        ->assertJsonPath('data.0.legal_form', 'SAS')
        ->assertJsonPath('data.0.siret', '12345678901234')
        ->assertJsonPath('data.0.vat_number', 'FR12345678901')
        ->assertJsonPath('data.0.billing_email', 'factu@alpha.test')
        ->assertJsonPath('data.0.address.line1', '12 rue de la Part-Dieu')
        ->assertJsonPath('data.0.address.line2', 'Bâtiment B')
        ->assertJsonPath('data.0.address.postal_code', '69003')
        ->assertJsonPath('data.0.address.city', 'Lyon')
        ->assertJsonPath('data.0.address.country', 'FR')
        ->assertJsonPath('data.0.payment_method', 'sepa')
        ->assertJsonPath('data.0.payment_method_label', 'Prélèvement SEPA')
        ->assertJsonPath('data.0.iban_last4', '1234');
});

it('expose une entité perso (facturation en nom propre) comme une entité comme une autre', function () {
    // PRD §3.3.2 (acté 13/09) : aucune exception — le particulier est le
    // billing_contact de sa propre entité `individual`.
    $user = User::factory()->billingContact()->create();
    $company = Company::factory()->individual()->create([
        'first_name' => 'Camille',
        'last_name' => 'Durand',
    ]);
    billingContactOf($user, $company);

    $this->actingAs($user)->getJson('/api/billing/entity')->assertOk()
        ->assertJsonPath('data.0.entity_type', 'individual')
        ->assertJsonPath('data.0.name', 'Camille Durand')
        ->assertJsonPath('data.0.legal_name', null);
});

it('ne divulgue jamais l\'IBAN complet ni le mandat SEPA (RGPD, CLAUDE.md §3.4)', function () {
    $user = User::factory()->billingContact()->create();
    $company = Company::factory()->create([
        'sepa_iban_last4' => '1234',
        'sepa_mandate_reference' => 'MANDAT-SECRET-001',
        'sepa_mandate_path' => 'mandates/secret.pdf',
        'discount_rate' => 12.5,
        'discount_note' => 'remise négociée confidentielle',
        'admin_notes' => 'note interne confidentielle',
    ]);
    billingContactOf($user, $company);

    $response = $this->actingAs($user)->getJson('/api/billing/entity')->assertOk();
    $raw = $response->getContent();

    // Le schéma ne stocke PAS l'IBAN complet : l'assertion porte donc sur tout
    // ce qui l'entoure (mandat, chemin du PDF) et sur les données internes.
    expect($raw)->not->toContain('MANDAT-SECRET-001')
        ->and($raw)->not->toContain('mandates/secret.pdf')
        ->and($raw)->not->toContain('remise négociée confidentielle')
        ->and($raw)->not->toContain('note interne confidentielle');

    $entity = $response->json('data.0');
    expect(array_keys($entity))->not->toContain('sepa_iban_last4', 'iban', 'sepa_mandate_reference', 'discount_rate', 'admin_notes')
        ->and($entity['iban_last4'])->toBe('1234');
});

it('refuse le bloc entité (403) à un membre sans rôle billing_contact', function () {
    $company = Company::factory()->create();
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create(['company_id' => $company->id]);

    $this->actingAs($user)->getJson('/api/billing/entity')->assertForbidden();
});

it('isole les entités entre contacts facturation (A ne voit pas l\'entité de B)', function () {
    $companyA = Company::factory()->create(['legal_name' => 'Alpha SAS']);
    $companyB = Company::factory()->create(['legal_name' => 'Beta SARL']);
    $userA = User::factory()->billingContact()->create();
    $userB = User::factory()->billingContact()->create();
    billingContactOf($userA, $companyA);
    billingContactOf($userB, $companyB);

    $names = collect(
        $this->actingAs($userA)->getJson('/api/billing/entity')->assertOk()->json('data')
    )->pluck('legal_name');

    expect($names)->toEqual(collect(['Alpha SAS']));
});

it('inclut l\'entité du profil membre quand le résident en est aussi contact facturation déclaré', function () {
    $company = Company::factory()->create(['legal_name' => 'Alpha SAS']);
    $user = User::factory()->resident()->create();
    $user->assignRole(Role::BillingContact->value);
    MemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
    billingContactOf($user, $company);

    $this->actingAs($user)->getJson('/api/billing/entity')->assertOk()
        ->assertJsonPath('data.0.legal_name', 'Alpha SAS');
});

it('exclut l\'entité où l\'utilisateur n\'est que résident, sans mandat de facturation', function () {
    // Faille corrigée (review lot D) : `linkedCompanyIds()` inclut l'entité du
    // profil membre — un contact facturation d'Alpha, résident de Beta, lisait
    // les coordonnées bancaires de Beta. Les données bancaires exigent un
    // `contacts.role = billing` sur CETTE entité.
    // SIRET figé : un SIRET aléatoire de 14 chiffres contient « 4242 » environ
    // une fois sur mille, ce qui faisait échouer l'assertion « la réponse ne
    // contient pas 4242 » au hasard des runs (vu en CI locale le 2026-09-20).
    $alpha = Company::factory()->create(['legal_name' => 'Alpha SAS', 'siret' => '11111111111111']);
    $beta = Company::factory()->create([
        'legal_name' => 'Beta SARL',
        'preferred_payment_method' => 'sepa',
        'sepa_iban_last4' => '4242',
    ]);

    $user = User::factory()->resident()->create();
    $user->assignRole(Role::BillingContact->value);
    billingContactOf($user, $alpha);
    MemberProfile::factory()->for($user)->create(['company_id' => $beta->id]);

    $response = $this->actingAs($user)->getJson('/api/billing/entity')->assertOk();

    expect(collect($response->json('data'))->pluck('legal_name'))->toEqual(collect(['Alpha SAS']))
        ->and($response->getContent())->not->toContain('4242');
});

it('n\'expose pas les coordonnées bancaires de l\'entité où l\'utilisateur n\'est que résident (profil)', function () {
    $beta = Company::factory()->create([
        'preferred_payment_method' => 'sepa',
        'sepa_iban_last4' => '4242',
    ]);
    $alpha = Company::factory()->create(['siret' => '11111111111111']); // SIRET figé, cf. test précédent

    $user = User::factory()->resident()->create();
    $user->assignRole(Role::BillingContact->value);
    billingContactOf($user, $alpha); // contact facturation d'une AUTRE entité
    MemberProfile::factory()->for($user)->create(['company_id' => $beta->id]);

    $response = $this->actingAs($user)->getJson('/api/profile')->assertOk();

    expect(array_keys((array) $response->json('company')))
        ->not->toContain('payment_method', 'iban_last4')
        ->and($response->getContent())->not->toContain('4242');
});

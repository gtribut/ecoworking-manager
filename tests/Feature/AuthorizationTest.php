<?php

declare(strict_types=1);

use App\Models\AdministrativeDocument;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Tests d'isolation des données (CLAUDE.md §3.1 — catégorie d'erreur inacceptable).
 * Garantit qu'un membre A n'accède jamais aux données d'un membre B / d'une autre
 * entité, et que les super-pouvoirs admin ainsi que la règle « facture émise
 * non supprimable » (§3.6) sont respectés.
 */

/** Crée un billing_contact rattaché à l'entité $company via un contact `billing`. */
function billingContactFor(Company $company): User
{
    $user = User::factory()->billingContact()->create();
    Contact::factory()->billing()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

// ---------------------------------------------------------------------------
// Entités possédées par un membre (isolation par user_id)
// ---------------------------------------------------------------------------

it('interdit à un membre de voir/modifier/supprimer la réservation d\'un autre', function () {
    $this->seed(PermissionSeeder::class); // resident → permission manage-own-booking
    $alice = User::factory()->resident()->create();
    $bob = User::factory()->resident()->create();
    $booking = Booking::factory()->for($alice)->create();

    expect($bob->can('view', $booking))->toBeFalse()
        ->and($bob->can('update', $booking))->toBeFalse()
        ->and($bob->can('delete', $booking))->toBeFalse()
        ->and($alice->can('view', $booking))->toBeTrue()
        ->and($alice->can('update', $booking))->toBeTrue();
});

it('autorise l\'admin à agir sur la réservation de n\'importe qui (super-pouvoir §2.6)', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->for(User::factory()->resident())->create();

    expect($admin->can('view', $booking))->toBeTrue()
        ->and($admin->can('update', $booking))->toBeTrue()
        ->and($admin->can('delete', $booking))->toBeTrue();
});

it('interdit de modifier une réservation passée ou annulée (propriétaire inclus)', function () {
    $alice = User::factory()->resident()->create();
    $past = Booking::factory()->for($alice)->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);
    $cancelled = Booking::factory()->for($alice)->cancelled()->create();

    expect($alice->can('update', $past))->toBeFalse()
        ->and($alice->can('update', $cancelled))->toBeFalse();
});

it('isole tickets, achats, occupations, absences et consentements entre membres', function () {
    $alice = User::factory()->resident()->create();
    $bob = User::factory()->resident()->create();

    $ticket = Ticket::factory()->for($alice)->create();
    $purchase = Purchase::factory()->for($alice)->create();
    $occupation = DeskOccupation::factory()->for($alice)->create();
    $absence = DeskAbsence::factory()->for($alice)->create();
    $consent = Consent::factory()->for($alice)->create();

    expect($bob->can('view', $ticket))->toBeFalse()
        ->and($bob->can('view', $purchase))->toBeFalse()
        ->and($bob->can('view', $occupation))->toBeFalse()
        ->and($bob->can('update', $occupation))->toBeFalse()
        ->and($bob->can('delete', $absence))->toBeFalse()
        ->and($bob->can('view', $consent))->toBeFalse()
        ->and($alice->can('view', $ticket))->toBeTrue()
        ->and($alice->can('update', $occupation))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Facturation (billing_contact + périmètre d'entité)
// ---------------------------------------------------------------------------

it('limite la visibilité des factures au billing_contact de l\'entité concernée', function () {
    $companyX = Company::factory()->create();
    $companyY = Company::factory()->create();

    $invoiceX = Invoice::factory()->issued()->create([
        'billable_type' => 'company', 'billable_id' => $companyX->id,
    ]);

    $billingX = billingContactFor($companyX);
    $billingY = billingContactFor($companyY);

    expect($billingX->can('view', $invoiceX))->toBeTrue()
        ->and($billingX->can('download', $invoiceX))->toBeTrue()
        ->and($billingY->can('view', $invoiceX))->toBeFalse()
        ->and($billingY->can('download', $invoiceX))->toBeFalse();
});

it('limite les données de facturation d\'une entité à son billing_contact (lot D)', function () {
    $this->seed(PermissionSeeder::class); // billing_contact → view-billing-section

    // PRD §3.6.4 : mode de paiement + IBAN-4 réservés au contact de facturation
    // de CETTE entité ; un résident rattaché lit l'entité mais pas ces champs.
    $companyX = Company::factory()->create();
    $companyY = Company::factory()->create();

    $billingX = billingContactFor($companyX);
    $billingY = billingContactFor($companyY);

    $resident = User::factory()->resident()->create();
    MemberProfile::factory()->for($resident)->create(['company_id' => $companyX->id]);

    expect($billingX->can('viewBillingDetails', $companyX))->toBeTrue()
        ->and($billingY->can('viewBillingDetails', $companyX))->toBeFalse()
        ->and($resident->can('viewBillingDetails', $companyX))->toBeFalse()
        // Lecture simple de l'entité : ouverte au membre rattaché (PRD §2.5).
        ->and($resident->can('view', $companyX))->toBeTrue()
        ->and($resident->can('view', $companyY))->toBeFalse()
        ->and($billingX->can('viewAnyBillingDetails', Company::class))->toBeTrue()
        ->and($resident->can('viewAnyBillingDetails', Company::class))->toBeFalse();
});

it('refuse les factures de son entité à un membre SANS rôle billing_contact', function () {
    $company = Company::factory()->create();
    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
    ]);

    // Résident rattaché à l'entité mais sans le rôle facturation (PRD §2.5 : ❌).
    $resident = User::factory()->resident()->create();
    MemberProfile::factory()->for($resident)->create(['company_id' => $company->id]);

    expect($resident->can('view', $invoice))->toBeFalse();
});

it('autorise l\'admin à voir toutes les factures', function () {
    $admin = User::factory()->admin()->create();
    $invoice = Invoice::factory()->issued()->create();

    expect($admin->can('view', $invoice))->toBeTrue();
});

it('interdit la suppression d\'une facture émise, même à l\'admin (CLAUDE.md §3.6)', function () {
    $admin = User::factory()->admin()->create();
    $issued = Invoice::factory()->issued()->create();
    $draft = Invoice::factory()->create(); // brouillon, sans numéro

    expect($admin->can('delete', $issued))->toBeFalse()
        ->and($admin->can('update', $issued))->toBeFalse()
        ->and($admin->can('delete', $draft))->toBeTrue()
        ->and($admin->can('update', $draft))->toBeTrue();
});

it('interdit à un membre de supprimer une facture (brouillon comme émise)', function () {
    $billing = billingContactFor($company = Company::factory()->create());
    $draft = Invoice::factory()->create([
        'billable_type' => 'company', 'billable_id' => $company->id,
    ]);

    expect($billing->can('delete', $draft))->toBeFalse()
        ->and($billing->can('update', $draft))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Entité juridique, documents administratifs, abonnements, paiements
// ---------------------------------------------------------------------------

it('ouvre la consultation de l\'entité aux membres rattachés, la modif à l\'admin seul', function () {
    $companyX = Company::factory()->create();
    $companyY = Company::factory()->create();

    $member = User::factory()->resident()->create();
    MemberProfile::factory()->for($member)->create(['company_id' => $companyX->id]);

    expect($member->can('view', $companyX))->toBeTrue()
        ->and($member->can('view', $companyY))->toBeFalse()
        ->and($member->can('update', $companyX))->toBeFalse();

    expect(User::factory()->admin()->create()->can('update', $companyX))->toBeTrue();
});

it('isole les documents administratifs par entité (billing_contact)', function () {
    $companyX = Company::factory()->create();
    $docX = AdministrativeDocument::factory()->create(['company_id' => $companyX->id]);

    $billingX = billingContactFor($companyX);
    $billingY = billingContactFor(Company::factory()->create());

    expect($billingX->can('view', $docX))->toBeTrue()
        ->and($billingY->can('view', $docX))->toBeFalse();
});

it('limite la visibilité d\'un abonnement au souscripteur et à l\'admin', function () {
    $alice = User::factory()->resident()->create();
    $bob = User::factory()->resident()->create();
    $subscription = Subscription::factory()->create([
        'subscriber_type' => 'user', 'subscriber_id' => $alice->id,
        'billable_type' => 'user', 'billable_id' => $alice->id,
    ]);

    expect($alice->can('view', $subscription))->toBeTrue()
        ->and($bob->can('view', $subscription))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('view', $subscription))->toBeTrue();
});

it('réserve les paiements à l\'admin', function () {
    $member = User::factory()->resident()->create();
    $payment = Payment::factory()->create();

    expect($member->can('view', $payment))->toBeFalse()
        ->and($member->can('viewAny', Payment::class))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('view', $payment))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Profil membre / annuaire (visibilité conditionnée à l'opt-in)
// ---------------------------------------------------------------------------

it('respecte l\'opt-in annuaire pour la visibilité des profils entre membres', function () {
    $this->seed(PermissionSeeder::class); // resident → permission view-annuaire

    $optIn = MemberProfile::factory()->inDirectory()->create();
    $optOut = MemberProfile::factory()->create(['show_in_directory' => false]);

    $viewer = User::factory()->resident()->create();

    expect($viewer->can('view', $optIn))->toBeTrue()
        ->and($viewer->can('view', $optOut))->toBeFalse()
        // Le propriétaire voit toujours son profil, opt-out compris.
        ->and($optOut->user->can('view', $optOut))->toBeTrue()
        // Un membre ne peut pas éditer le profil d'un autre.
        ->and($viewer->can('update', $optIn))->toBeFalse();
});

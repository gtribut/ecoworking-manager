<?php

declare(strict_types=1);

use App\Models\AdministrativeDocument;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Lot B — Rôles & navigation (PRD §2.5). Contrepartie back du gating SPA :
 * `has_desk` dans /api/user (le « résident » du portail = celui qui a un bureau
 * attitré, pas celui qui a `create-own-booking`) et refus explicites (403) des
 * modules interdits à un rôle, plutôt qu'une liste vide trompeuse.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

/** Résident doté d'un bureau attitré (`member_profiles.desk_id` → resource desk). */
function gatingResidentWithDesk(): User
{
    $user = User::factory()->resident()->create();
    $desk = Resource::factory()->assignedResident()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);

    return $user;
}

/** Contact facturation « pur » (aucun rôle d'usage) rattaché à une entité. */
function gatingBillingOnly(?Company $company = null): User
{
    $user = User::factory()->billingContact()->create();
    Contact::factory()->billing()->create([
        'user_id' => $user->id,
        'company_id' => ($company ?? Company::factory()->create())->id,
    ]);

    return $user;
}

// ---------------------------------------------------------------------------
// GET /api/user — has_desk
// ---------------------------------------------------------------------------

it('expose has_desk = true pour un résident avec bureau attitré', function () {
    $this->actingAs(gatingResidentWithDesk())
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('has_desk', true);
});

it('expose has_desk = false sans bureau attitré, quel que soit le rôle', function (string $trait) {
    $user = User::factory()->{$trait}()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => null]);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('has_desk', false);
})->with(['resident', 'additional', 'external', 'staff']);

it('expose has_desk = false pour un contact facturation pur (aucun profil membre)', function () {
    $this->actingAs(gatingBillingOnly())
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('has_desk', false);
});

it('expose has_desk = false si la ressource rattachée n\'est pas un bureau', function () {
    $user = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $room->id]);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('has_desk', false);
});

it('n\'ajoute que has_desk à la projection de /api/user', function () {
    $response = $this->actingAs(gatingResidentWithDesk())->getJson('/api/user')->assertOk();

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'id', 'first_name', 'last_name', 'email', 'theme',
        'two_factor_enabled', 'roles', 'permissions', 'has_desk',
    ]);
});

// ---------------------------------------------------------------------------
// Présence / absences — réservées au membre doté d'un bureau attitré
// ---------------------------------------------------------------------------

it('ouvre la présence et les absences au résident avec bureau attitré', function () {
    $user = gatingResidentWithDesk();
    $today = now()->toDateString();

    $this->actingAs($user)->getJson("/api/presence?from={$today}&to={$today}")->assertOk();

    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => now()->addDay()->toDateString(),
        'period' => 'full_day',
    ])->assertCreated();
});

it('refuse la présence (403) à un membre sans bureau attitré', function (string $trait) {
    $user = User::factory()->{$trait}()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => null]);
    $today = now()->toDateString();

    $this->actingAs($user)->getJson("/api/presence?from={$today}&to={$today}")->assertForbidden();

    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => now()->addDay()->toDateString(),
        'period' => 'full_day',
    ])->assertForbidden();
})->with(['resident', 'additional', 'external']);

it('refuse la présence (403) à un contact facturation pur', function () {
    $user = gatingBillingOnly();
    $today = now()->toDateString();

    $this->actingAs($user)->getJson("/api/presence?from={$today}&to={$today}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => now()->addDay()->toDateString(),
        'period' => 'full_day',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Réservations & salles — interdites au contact facturation pur
// ---------------------------------------------------------------------------

it('refuse les salles et réservations (403) à un contact facturation pur', function () {
    $user = gatingBillingOnly();
    $room = Resource::factory()->meetingRoom()->create();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->getJson('/api/rooms')->assertForbidden();
    $this->actingAs($user)->getJson("/api/rooms/{$room->id}/availability?date={$date}")->assertForbidden();
    $this->actingAs($user)->getJson("/api/rooms/availability?from={$date}&to={$date}")->assertForbidden();
    $this->actingAs($user)->getJson('/api/bookings')->assertForbidden();
});

it('laisse les salles et réservations aux rôles d\'usage', function (string $trait) {
    $user = User::factory()->{$trait}()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->getJson('/api/rooms')->assertOk();
    $this->actingAs($user)->getJson("/api/rooms/{$room->id}/availability?date={$date}")->assertOk();
    $this->actingAs($user)->getJson("/api/rooms/availability?from={$date}&to={$date}")->assertOk();
    $this->actingAs($user)->getJson('/api/bookings')->assertOk();
})->with(['resident', 'additional', 'external', 'staff']);

// ---------------------------------------------------------------------------
// Tickets & bureaux nomades — réservés à l'external (ou l'admin, lot E pt.3)
// ---------------------------------------------------------------------------

it('refuse tickets et disponibilité bureau (403) aux rôles sans create-paid-booking', function (string $trait) {
    $user = User::factory()->{$trait}()->create();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->getJson('/api/tickets')->assertForbidden();
    $this->actingAs($user)->getJson("/api/desks/availability?date={$date}&period=morning")->assertForbidden();
})->with(['resident', 'additional', 'staff']);

it('refuse tickets et disponibilité bureau (403) à un contact facturation pur', function () {
    $user = gatingBillingOnly();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->getJson('/api/tickets')->assertForbidden();
    $this->actingAs($user)->getJson("/api/desks/availability?date={$date}&period=morning")->assertForbidden();
});

it('ouvre tickets et disponibilité bureau à l\'external', function () {
    $user = User::factory()->external()->create();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->getJson('/api/tickets')->assertOk();
    $this->actingAs($user)->getJson("/api/desks/availability?date={$date}&period=morning")->assertOk();
});

// ---------------------------------------------------------------------------
// Facturation — module refusé (403) sans le rôle billing_contact
// ---------------------------------------------------------------------------

it('refuse factures et documents administratifs (403) sans rôle billing_contact', function (string $trait) {
    $company = Company::factory()->create();
    $user = User::factory()->{$trait}()->create();
    MemberProfile::factory()->for($user)->create(['company_id' => $company->id]);
    Invoice::factory()->issued()->create(['billable_type' => 'company', 'billable_id' => $company->id]);
    AdministrativeDocument::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->getJson('/api/invoices')->assertForbidden();
    $this->actingAs($user)->getJson('/api/documents/administrative')->assertForbidden();
})->with(['resident', 'additional', 'external', 'staff']);

it('gate le module administratif par Policy (viewAny), pas par un test de rôle inline', function () {
    $billing = gatingBillingOnly();
    $resident = User::factory()->resident()->create();
    $admin = User::factory()->admin()->create();

    expect($billing->can('viewAny', Invoice::class))->toBeTrue()
        ->and($billing->can('viewAny', AdministrativeDocument::class))->toBeTrue()
        ->and($resident->can('viewAny', Invoice::class))->toBeFalse()
        ->and($resident->can('viewAny', AdministrativeDocument::class))->toBeFalse()
        ->and($admin->can('viewAny', Invoice::class))->toBeTrue()
        ->and($admin->can('viewAny', AdministrativeDocument::class))->toBeTrue();
});

it('ouvre factures et documents administratifs au contact facturation', function () {
    $company = Company::factory()->create();
    $user = gatingBillingOnly($company);
    Invoice::factory()->issued()->create(['billable_type' => 'company', 'billable_id' => $company->id]);
    AdministrativeDocument::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->getJson('/api/invoices')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user)->getJson('/api/documents/administrative')->assertOk()->assertJsonCount(1, 'data');
});

<?php

declare(strict_types=1);

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Models\Company;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Services\FloorPlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * C12.5 — Annuaire des coworkers + plan des étages (PRD §3.7).
 *
 * Garanties testées : accès réservé à `view-annuaire` (les external → 403),
 * annuaire limité aux profils OPT-IN (`show_in_directory`) en projection
 * minimale (jamais email/téléphone), occupation du plan cohérente avec
 * PresenceService/occupations external, opt-out anonymisé aussi sur le plan.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // rôles → permissions (view-annuaire)
});

/** Mercredi 1er juillet 2026 : jour ouvré, pas férié. */
const PLAN_DATE = '2026-07-01';

/** Résident opt-in annuaire avec bureau attitré. */
function residentWithDesk(array $profile = [], array $desk = []): MemberProfile
{
    $resource = Resource::factory()->assignedResident()->create($desk + ['floor' => 1]);

    return MemberProfile::factory()
        ->inDirectory()
        ->withDesk($resource->id)
        ->create($profile + ['user_id' => User::factory()->resident()->create()->id]);
}

// --- Auth & rôles ------------------------------------------------------------

it('protège l\'annuaire et le plan : 401 si non authentifié', function () {
    $this->getJson('/api/directory')->assertUnauthorized();
    $this->getJson('/api/directory/floor-plan')->assertUnauthorized();
});

it('refuse l\'annuaire et le plan aux external (403, PRD §3.7.1)', function () {
    $external = User::factory()->external()->create();

    $this->actingAs($external)->getJson('/api/directory')->assertForbidden();
    $this->actingAs($external)->getJson('/api/directory/floor-plan')->assertForbidden();
});

it('ouvre l\'annuaire aux resident, additional, staff et admin', function () {
    foreach (['resident', 'additional', 'staff', 'admin'] as $role) {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->getJson('/api/directory')->assertOk();
        $this->actingAs($user)->getJson('/api/directory/floor-plan')->assertOk();
    }
});

// --- Annuaire : opt-in et projection ------------------------------------------

it('ne liste que les membres opt-in actifs (opt-out et partis invisibles)', function () {
    $optIn = MemberProfile::factory()->inDirectory()->create();
    $optOut = MemberProfile::factory()->create(['show_in_directory' => false]);
    $left = MemberProfile::factory()->inDirectory()->paused()->create();

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($optIn->id)
        ->not->toContain($optOut->id)
        ->not->toContain($left->id);
});

it('expose une projection minimale : jamais d\'email ni de téléphone', function () {
    MemberProfile::factory()->inDirectory()->create();

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory')
        ->assertOk();

    $entry = $response->json('data.0');

    expect($entry)->toHaveKeys(['id', 'first_name', 'last_name', 'photo', 'job_title', 'bio', 'company'])
        ->and(array_keys($entry))->not->toContain('email')
        ->and(array_keys($entry))->not->toContain('phone')
        ->and(json_encode($entry))->not->toContain('@'); // aucune adresse email sérialisée
});

it('recherche dans l\'annuaire par nom, entreprise ou poste', function () {
    $company = Company::factory()->create(['legal_name' => 'Fromagerie Dupontel']);
    $target = MemberProfile::factory()->inDirectory()->create([
        'company_id' => $company->id,
        'user_id' => User::factory()->resident()->create(['last_name' => 'Vercingétorix'])->id,
    ]);
    MemberProfile::factory()->inDirectory()->create(); // bruit

    $viewer = User::factory()->resident()->create();

    $byName = $this->actingAs($viewer)->getJson('/api/directory?q=vercing')->assertOk();
    expect(collect($byName->json('data'))->pluck('id')->all())->toBe([$target->id]);

    $byCompany = $this->actingAs($viewer)->getJson('/api/directory?q=fromagerie')->assertOk();
    expect(collect($byCompany->json('data'))->pluck('id')->all())->toBe([$target->id]);
});

// --- Plan des étages : occupation --------------------------------------------

it('marque présent le résident attitré un jour ouvré sans absence, avec sa fiche opt-in', function () {
    $profile = residentWithDesk();

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk()
        ->assertJsonPath('date', PLAN_DATE)
        ->assertJsonPath('is_working_day', true);

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['status'])->toBe('present')
        ->and($desk['present_period'])->toBeNull()
        ->and($desk['assignment'])->toBe('assigned_resident')
        ->and($desk['occupant']['visible'])->toBeTrue()
        ->and($desk['occupant']['first_name'])->toBe($profile->user->first_name)
        ->and($desk['occupant']['company'])->toBe($profile->company->name);
});

it('marque absent le résident avec absence déclarée (titulaire toujours identifié si opt-in)', function () {
    $profile = residentWithDesk();
    DeskAbsence::factory()->create([
        'desk_id' => $profile->desk_id,
        'user_id' => $profile->user_id,
        'date_start' => PLAN_DATE,
        'date_end' => null,
        'period' => Period::FullDay->value,
    ]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['status'])->toBe('absent')
        ->and($desk['occupant']['visible'])->toBeTrue()
        ->and($desk['occupant']['last_name'])->toBe($profile->user->last_name);
});

it('signale une absence demi-journée comme présence partielle', function () {
    $profile = residentWithDesk();
    DeskAbsence::factory()->create([
        'desk_id' => $profile->desk_id,
        'user_id' => $profile->user_id,
        'date_start' => PLAN_DATE,
        'date_end' => null,
        'period' => Period::Morning->value,
    ]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    // Absent le matin => présent l'après-midi seulement (recette R-08).
    expect($desk['status'])->toBe('partial')
        ->and($desk['present_period'])->toBe('afternoon');
});

it('anonymise le titulaire opt-out sur le plan : aucune donnée personnelle', function () {
    $profile = residentWithDesk(['show_in_directory' => false]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['status'])->toBe('present')
        ->and($desk['occupant'])->toBe(['visible' => false]);
});

it('marque libre un bureau non attitré sans occupation, occupé par un external avec ticket sinon', function () {
    $free = Resource::factory()->desk()->create(['floor' => 2]);
    $taken = Resource::factory()->desk()->create(['floor' => 2]);

    $external = User::factory()->external()->create();
    MemberProfile::factory()->inDirectory()->create(['user_id' => $external->id]);
    DeskOccupation::factory()->create([
        'desk_id' => $taken->id,
        'user_id' => $external->id,
        'date' => PLAN_DATE,
        'period' => Period::FullDay->value,
        'source' => DeskOccupationSource::ExternalTicket->value,
        'status' => DeskOccupationStatus::Present->value,
    ]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $desks = collect($response->json('desks'));

    expect($desks->firstWhere('resource_id', $free->id)['status'])->toBe('free')
        ->and($desks->firstWhere('resource_id', $free->id)['occupant'])->toBeNull()
        ->and($desks->firstWhere('resource_id', $taken->id)['status'])->toBe('present')
        ->and($desks->firstWhere('resource_id', $taken->id)['occupant']['visible'])->toBeTrue()
        ->and($desks->firstWhere('resource_id', $taken->id)['occupant']['first_name'])->toBe($external->first_name);
});

it('ignore les occupations annulées et marque partielle une occupation demi-journée', function () {
    $desk = Resource::factory()->desk()->create();
    $external = User::factory()->external()->create();

    DeskOccupation::factory()->create([
        'desk_id' => $desk->id,
        'user_id' => $external->id,
        'date' => PLAN_DATE,
        'period' => Period::Morning->value,
        'source' => DeskOccupationSource::ExternalTicket->value,
        'status' => DeskOccupationStatus::Present->value,
    ]);
    DeskOccupation::factory()->create([
        'desk_id' => $desk->id,
        'user_id' => $external->id,
        'date' => PLAN_DATE,
        'period' => Period::Afternoon->value,
        'source' => DeskOccupationSource::ExternalTicket->value,
        'status' => DeskOccupationStatus::Cancelled->value,
    ]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $state = collect($response->json('desks'))->firstWhere('resource_id', $desk->id);

    expect($state['status'])->toBe('partial')
        // Seule l'occupation du matin tient (l'autre est annulée) — R-08.
        ->and($state['present_period'])->toBe('morning')
        // External sans opt-in annuaire : présence connue mais identité masquée.
        ->and($state['occupant'])->toBe(['visible' => false]);
});

it('marque hors service un bureau désactivé, sans occupant', function () {
    $desk = Resource::factory()->desk()->outOfService()->create();

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $state = collect($response->json('desks'))->firstWhere('resource_id', $desk->id);

    expect($state['status'])->toBe('out_of_service')
        ->and($state['occupant'])->toBeNull();
});

it('signale SON bureau au membre (is_own) pour le raccourci « gérer mes absences »', function () {
    $profile = residentWithDesk();

    $response = $this->actingAs($profile->user)
        ->getJson('/api/directory/floor-plan?date='.PLAN_DATE)
        ->assertOk();

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['is_own'])->toBeTrue();
});

// Ré-acté 2026-09-17 : `is_working_day` reste exposé (bureaux nomades non
// réservables) mais ne rend plus les bureaux attitrés absents.
it('garde les bureaux attitrés présents un jour non ouvré, tout en le signalant', function () {
    $profile = residentWithDesk();

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date=2026-07-05') // dimanche
        ->assertOk()
        ->assertJsonPath('is_working_day', false);

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['status'])->toBe('present');
});

it('marque le bureau attitré absent un jour non ouvré couvert par une absence', function () {
    $profile = residentWithDesk();
    DeskAbsence::factory()->for($profile->user)->create([
        'desk_id' => $profile->desk_id,
        'date_start' => '2026-07-05', // dimanche
        'period' => Period::FullDay->value,
    ]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date=2026-07-05')
        ->assertOk();

    $desk = collect($response->json('desks'))->firstWhere('resource_id', $profile->desk_id);

    expect($desk['status'])->toBe('absent');
});

it('rejette une date invalide (422)', function () {
    $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/directory/floor-plan?date=demain')
        ->assertUnprocessable();
});

// --- Perf : pas de N+1 --------------------------------------------------------

it('calcule le plan en un nombre de requêtes constant quel que soit le nombre de bureaux', function () {
    foreach (range(1, 8) as $i) {
        residentWithDesk(desk: ['display_order' => $i]);
    }
    $viewer = User::factory()->resident()->create();
    $viewer->loadMissing('memberProfile');

    $service = app(FloorPlanService::class);

    DB::enableQueryLog();
    $service->forDate(CarbonImmutable::parse(PLAN_DATE), $viewer);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Attendu : bureaux + eager loads (profils, users, companies) + absences
    // + occupations = 6 requêtes, indépendant du nombre de bureaux (8 ici).
    expect($count)->toBeLessThanOrEqual(8);
});

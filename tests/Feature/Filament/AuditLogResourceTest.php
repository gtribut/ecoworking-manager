<?php

declare(strict_types=1);

use App\Filament\Resources\ActivityLog\ActivityResource;
use App\Filament\Resources\ActivityLog\Pages\ListActivities;
use App\Filament\Resources\ActivityLog\Pages\ViewActivity;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * C12.8b — Audit log UI (PRD §4.14) : Resource Filament en LECTURE SEULE sur
 * `activity_log`. Les entrées sont produites par le trait Auditable (testé
 * dans AuditLogTest) — ici on exerce la consultation : liste, filtres
 * (modèle, action, auteur, période, contenu du diff), détail du diff, et le
 * verrouillage lecture seule / admin-only (ActivityPolicy).
 * Rendu testé au niveau composant Livewire (le 2FA obligatoire casse le HTTP).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->admin()->create();
    actingAs($this->admin);
});

// --- Liste & filtres ---------------------------------------------------------

it('rend la liste des entrées d\'audit pour un admin', function () {
    $company = Company::factory()->create();
    $company->update(['status' => 'inactive']);

    Livewire::test(ListActivities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Activity::all());
});

it('filtre par modèle (subject_type) et par action (event)', function () {
    $company = Company::factory()->create();
    $company->update(['status' => 'inactive']);

    $companyCreated = Activity::forSubject($company)->forEvent('created')->firstOrFail();
    $companyUpdated = Activity::forSubject($company)->forEvent('updated')->firstOrFail();
    // Création de l'admin en beforeEach → activité subject_type=user.
    $userCreated = Activity::forSubject($this->admin)->forEvent('created')->firstOrFail();

    Livewire::test(ListActivities::class)
        ->filterTable('subject_type', 'company')
        ->assertCanSeeTableRecords([$companyCreated, $companyUpdated])
        ->assertCanNotSeeTableRecords([$userCreated])
        ->resetTableFilters()
        ->filterTable('event', 'updated')
        ->assertCanSeeTableRecords([$companyUpdated])
        ->assertCanNotSeeTableRecords([$companyCreated, $userCreated]);
});

it('filtre par auteur (causer)', function () {
    // L'admin authentifié est le causer des activités qu'il déclenche.
    $company = Company::factory()->create();
    $byAdmin = Activity::forSubject($company)->forEvent('created')->firstOrFail();
    expect($byAdmin->causer_id)->toBe($this->admin->id);

    // L'activité de création de l'admin lui-même n'a pas de causer (beforeEach,
    // avant actingAs) : elle doit disparaître du filtre.
    $noCauser = Activity::forSubject($this->admin)->forEvent('created')->firstOrFail();
    expect($noCauser->causer_id)->toBeNull();

    Livewire::test(ListActivities::class)
        ->filterTable('causer_id', $this->admin->id)
        ->assertCanSeeTableRecords([$byAdmin])
        ->assertCanNotSeeTableRecords([$noCauser]);
});

it('filtre par période', function () {
    $company = Company::factory()->create();
    $recent = Activity::forSubject($company)->forEvent('created')->firstOrFail();

    $old = Activity::forSubject($this->admin)->forEvent('created')->firstOrFail();
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    Livewire::test(ListActivities::class)
        ->filterTable('created_at', ['from' => now()->subDay()->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old])
        ->resetTableFilters()
        ->filterTable('created_at', ['until' => now()->subDays(7)->toDateString()])
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$recent]);
});

it('recherche dans le contenu des changements (PRD §4.14)', function () {
    $company = Company::factory()->create();
    $company->update(['status' => 'inactive']);

    $updated = Activity::forSubject($company)->forEvent('updated')->firstOrFail();
    $created = Activity::forSubject($company)->forEvent('created')->firstOrFail();

    Livewire::test(ListActivities::class)
        ->filterTable('changes', ['value' => 'inactive'])
        ->assertCanSeeTableRecords([$updated])
        ->assertCanNotSeeTableRecords([$created]);
});

// --- Détail (diff) -----------------------------------------------------------

it('affiche le détail du diff avant / après', function () {
    $company = Company::factory()->create();
    $company->update(['status' => 'inactive']);

    $activity = Activity::forSubject($company)->forEvent('updated')->firstOrFail();

    Livewire::test(ViewActivity::class, ['record' => $activity->getKey()])
        ->assertOk()
        ->assertSee('status')
        ->assertSee('active')
        ->assertSee('inactive')
        ->assertSee('Modification');
});

// --- Lecture seule & isolation -----------------------------------------------

it('est strictement en lecture seule (aucune création / édition / suppression)', function () {
    $company = Company::factory()->create();
    $activity = Activity::forSubject($company)->forEvent('created')->firstOrFail();

    expect(ActivityResource::canCreate())->toBeFalse()
        ->and(ActivityResource::getPages())->not->toHaveKeys(['create', 'edit'])
        // Même un admin ne peut ni créer, ni modifier, ni supprimer une entrée.
        ->and($this->admin->can('create', Activity::class))->toBeFalse()
        ->and($this->admin->can('update', $activity))->toBeFalse()
        ->and($this->admin->can('delete', $activity))->toBeFalse();
});

it('refuse à un membre la liste de l\'audit log (ActivityPolicy::viewAny)', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListActivities::class)->assertForbidden();
});

it('refuse à un membre le détail d\'une entrée (ActivityPolicy::view)', function () {
    $company = Company::factory()->create();
    $activity = Activity::forSubject($company)->forEvent('created')->firstOrFail();

    actingAs(User::factory()->member()->create());

    Livewire::test(ViewActivity::class, ['record' => $activity->getKey()])->assertForbidden();
});

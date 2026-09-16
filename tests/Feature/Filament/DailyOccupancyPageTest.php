<?php

declare(strict_types=1);

use App\Enums\Period;
use App\Filament\Pages\DailyOccupancy;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.6 — page Filament « Occupation du jour » (PRD §4.8.4). Testée au niveau
 * composant Livewire (le 2FA obligatoire casse l'accès HTTP). La logique vit
 * dans DailyOccupancyService (testé séparément).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

/** Prochain jour ouvré (nom unique à ce fichier). */
function occupancyPageWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

it('rend la page avec les bureaux attitrés et la capacité restante', function () {
    $desk = Resource::factory()->assignedResident()->create(['name' => 'Bureau 12', 'floor' => 1]);
    $resident = User::factory()->create(['first_name' => 'Jeanne', 'last_name' => 'Moulin']);
    MemberProfile::factory()->for($resident)->create(['desk_id' => $desk->id]);
    Resource::factory()->desk()->create(['name' => 'Bureau libre 3', 'floor' => 1]);

    Livewire::test(DailyOccupancy::class)
        ->assertOk()
        ->assertSee('Étage 1')
        ->assertSee('Bureau 12')
        ->assertSee('Jeanne Moulin')
        ->assertSee('Bureau libre 3')
        ->assertSee('Capacité bureaux libres restante');
});

it('réagit au changement de date : le résident devient absent', function () {
    $day = occupancyPageWorkingDay();
    $desk = Resource::factory()->assignedResident()->create(['floor' => 1]);
    $resident = User::factory()->create();
    MemberProfile::factory()->for($resident)->create(['desk_id' => $desk->id]);
    DeskAbsence::factory()->for($resident)->create([
        'desk_id' => $desk->id,
        'date_start' => $day->toDateString(),
        'period' => Period::FullDay->value,
    ]);

    Livewire::test(DailyOccupancy::class)
        ->set('date', $day->toDateString())
        ->assertSee('Absent');
});

// Ré-acté 2026-09-17 : un jour non ouvré ne concerne plus que les bureaux
// nomades — l'encart ne doit plus dire que les résidents ne sont pas attendus.
it('affiche un encart limité aux bureaux nomades un jour non ouvré', function () {
    $desk = Resource::factory()->assignedResident()->create(['floor' => 1]);
    $resident = User::factory()->create();
    MemberProfile::factory()->for($resident)->create(['desk_id' => $desk->id]);
    $sunday = CarbonImmutable::today()->next('sunday');

    Livewire::test(DailyOccupancy::class)
        ->set('date', $sunday->toDateString())
        ->assertOk()
        ->assertSee('les bureaux nomades ne sont pas réservables')
        ->assertDontSee('les résidents ne sont pas attendus');
});

it('retombe sur aujourd\'hui si la date saisie est invalide (pas de 500)', function () {
    Resource::factory()->assignedResident()->create(['floor' => 1]);

    Livewire::test(DailyOccupancy::class)
        ->set('date', 'n-importe-quoi')
        ->assertOk();
});

it('refuse l\'accès à un membre non admin', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(DailyOccupancy::class)->assertForbidden();
});

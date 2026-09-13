<?php

declare(strict_types=1);

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Filament\Resources\DeskAbsences\Pages\CreateDeskAbsence;
use App\Filament\Resources\DeskAbsences\Pages\EditDeskAbsence;
use App\Filament\Resources\DeskAbsences\Pages\ListDeskAbsences;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * Lot C — Resource Filament « Absences bureaux » (PRD §4.8.2). Rendu testé au
 * niveau composant Livewire (le panel impose le 2FA en HTTP, cf. mémo C3.x).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Résident doté d'un bureau attitré. */
function absenceResident(): User
{
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create([
        'desk_id' => Resource::factory()->assignedResident()->create()->id,
    ]);

    return $user;
}

it('rend la liste, la création et l\'édition pour un admin', function () {
    actingAs(User::factory()->admin()->create());
    $absence = DeskAbsence::factory()->create();

    Livewire::test(ListDeskAbsences::class)->assertOk();
    Livewire::test(CreateDeskAbsence::class)->assertOk();
    Livewire::test(EditDeskAbsence::class, ['record' => $absence->getRouteKey()])->assertOk();
});

it('refuse la liste à un membre (DeskAbsencePolicy)', function () {
    actingAs(User::factory()->additional()->create());

    Livewire::test(ListDeskAbsences::class)->assertForbidden();
});

it('filtre par défaut sur les absences à venir ou en cours', function () {
    actingAs(User::factory()->admin()->create());
    $today = CarbonImmutable::today();

    $past = DeskAbsence::factory()->create([
        'date_start' => $today->subMonth()->toDateString(),
        'date_end' => $today->subWeeks(3)->toDateString(),
    ]);
    // Jour unique passé : `date_end` NULL — la clause doit rester NULL-safe,
    // sinon cette ligne disparaît des DEUX filtres.
    $pastSingleDay = DeskAbsence::factory()->create([
        'date_start' => $today->subDays(2)->toDateString(),
        'date_end' => null,
    ]);
    // Récurrence hebdo BORNÉE et terminée : passée elle aussi.
    $endedWeekly = DeskAbsence::factory()->weekly()->create([
        'date_start' => $today->subMonths(3)->toDateString(),
        'date_end' => $today->subMonth()->toDateString(),
    ]);
    $upcoming = DeskAbsence::factory()->create([
        'date_start' => $today->addWeek()->toDateString(),
        'date_end' => null,
    ]);

    Livewire::test(ListDeskAbsences::class)
        ->assertCanSeeTableRecords([$upcoming])
        ->assertCanNotSeeTableRecords([$past, $pastSingleDay, $endedWeekly])
        ->filterTable('period_status', 'past')
        ->assertCanSeeTableRecords([$past, $pastSingleDay, $endedWeekly])
        ->assertCanNotSeeTableRecords([$upcoming])
        // Option vide du select = « toutes ».
        ->filterTable('period_status', null)
        ->assertCanSeeTableRecords([$past, $pastSingleDay, $endedWeekly, $upcoming]);
});

it('réserve le panneau aux détenteurs de declare-presence-for-others', function () {
    $this->seed(PermissionSeeder::class);
    $resident = absenceResident();

    actingAs($resident);
    expect(DeskAbsenceResource::canAccess())->toBeFalse();

    actingAs(User::factory()->admin()->create());
    expect(DeskAbsenceResource::canAccess())->toBeTrue();
});

it('crée une absence pour un membre via le PresenceService, sans notifier les admins', function () {
    Notification::fake();
    actingAs(User::factory()->admin()->create());
    $member = absenceResident();
    $today = CarbonImmutable::today();

    Livewire::test(CreateDeskAbsence::class)
        ->fillForm([
            'user_id' => $member->id,
            'date_start' => $today->addDays(3)->toDateString(),
            'date_end' => $today->addDays(5)->toDateString(),
            'period' => Period::Afternoon->value,
            'recurrence_type' => DeskAbsenceRecurrence::None->value,
            'notes' => 'Rendez-vous chantier — saisi par l’accueil',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $absence = DeskAbsence::query()->where('user_id', $member->id)->sole();

    expect($absence->desk_id)->toBe($member->memberProfile->desk_id)
        ->and($absence->period)->toBe(Period::Afternoon)
        ->and($absence->notes)->toBe('Rendez-vous chantier — saisi par l’accueil');

    // Q25 : la notification admin ne concerne QUE les déclarations portail.
    Notification::assertNothingSent();
});

it('trace la correction et la suppression admin dans l\'audit log', function () {
    actingAs(User::factory()->admin()->create());
    $member = absenceResident();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $member->id,
        'desk_id' => $member->memberProfile->desk_id,
        'date_start' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'date_end' => null,
        'period' => Period::FullDay->value,
    ]);

    Livewire::test(EditDeskAbsence::class, ['record' => $absence->getRouteKey()])
        ->fillForm(['period' => Period::Morning->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($absence->fresh()->period)->toBe(Period::Morning)
        ->and(Activity::forSubject($absence)->forEvent('updated')->exists())->toBeTrue();
});

it('supprime une absence passée depuis le back-office, en la traçant', function () {
    actingAs(User::factory()->admin()->create());
    $member = absenceResident();
    $today = CarbonImmutable::today();
    // Absence PASSÉE : interdite au membre (403 côté portail), permise à l'admin.
    $absence = DeskAbsence::factory()->create([
        'user_id' => $member->id,
        'desk_id' => $member->memberProfile->desk_id,
        'date_start' => $today->subWeeks(2)->toDateString(),
        'date_end' => $today->subWeek()->toDateString(),
    ]);
    $id = $absence->id;

    Livewire::test(EditDeskAbsence::class, ['record' => $absence->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(DeskAbsence::query()->whereKey($id)->exists())->toBeFalse()
        ->and(Activity::query()->where('subject_type', 'desk_absence')->where('subject_id', $id)
            ->where('event', 'deleted')->exists())->toBeTrue();
});

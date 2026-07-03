<?php

declare(strict_types=1);

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Services\DailyOccupancyService;
use App\Services\PresenceService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * C12.6 — vue « Occupation du jour » (PRD §4.8.4). La page Filament est
 * mince : c'est CE service qui porte le comportement, en nombre CONSTANT
 * de requêtes (finding perf M8).
 */
function occupancyService(): DailyOccupancyService
{
    return app(DailyOccupancyService::class);
}

/** Prochain jour ouvré (nom distinct de nextWorkingDay(), défini ailleurs). */
function occupancyWorkingDay(): CarbonImmutable
{
    $d = CarbonImmutable::today()->addDay();
    while (! FrenchHolidays::isWorkingDay($d)) {
        $d = $d->addDay();
    }

    return $d;
}

/** Crée un bureau attitré avec son résident (profil rattaché). */
function residentDesk(int $floor = 1): array
{
    $desk = Resource::factory()->assignedResident()->create(['floor' => $floor]);
    $user = User::factory()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);

    return [$desk, $user];
}

it('classe les bureaux attitrés par étage avec statut présent / absent / pas d\'info', function () {
    $day = occupancyWorkingDay();

    [$present] = residentDesk(floor: 1);
    [$absentDesk, $absentUser] = residentDesk(floor: 1);
    DeskAbsence::factory()->for($absentUser)->create([
        'desk_id' => $absentDesk->id,
        'date_start' => $day->toDateString(),
        'period' => Period::FullDay->value,
    ]);
    // Bureau attitré SANS profil rattaché → « pas d'info ».
    $orphan = Resource::factory()->assignedResident()->create(['floor' => 2]);

    $result = occupancyService()->forDate($day);

    expect($result['is_working_day'])->toBeTrue()
        ->and(array_keys($result['floors']))->toBe([1, 2]);

    $floor1 = collect($result['floors'][1]['assigned'])->keyBy(fn (array $e): int => $e['desk']->id);

    expect($floor1[$present->id]['status'])->toBe('present')
        ->and($floor1[$absentDesk->id]['status'])->toBe('absent')
        ->and($result['floors'][2]['assigned'][0]['desk']->id)->toBe($orphan->id)
        ->and($result['floors'][2]['assigned'][0]['status'])->toBe('unknown');
});

it('marque tous les résidents absents un jour non ouvré', function () {
    residentDesk();
    $sunday = CarbonImmutable::today()->next('sunday');

    $result = occupancyService()->forDate($sunday);

    expect($result['is_working_day'])->toBeFalse()
        ->and($result['floors'][1]['assigned'][0]['status'])->toBe('absent');
});

it('liste les bureaux libres avec leur external ou disponibles, et la capacité restante', function () {
    $day = occupancyWorkingDay();

    $taken = Resource::factory()->desk()->create(['floor' => 1]);
    $free = Resource::factory()->desk()->create(['floor' => 1]);
    $external = User::factory()->create();
    DeskOccupation::factory()->create([
        'desk_id' => $taken->id,
        'user_id' => $external->id,
        'date' => $day->toDateString(),
        'period' => Period::FullDay->value,
    ]);

    $result = occupancyService()->forDate($day);
    $unassigned = collect($result['floors'][1]['unassigned'])->keyBy(fn (array $e): int => $e['desk']->id);

    expect($unassigned[$taken->id]['occupations']->first()->user_id)->toBe($external->id)
        ->and($unassigned[$free->id]['occupations'])->toBeEmpty()
        ->and($result['available_desks_count'])->toBe(1);
});

it('montre l\'external qui utilise le bureau attitré d\'un résident absent', function () {
    $day = occupancyWorkingDay();

    [$desk, $resident] = residentDesk();
    DeskAbsence::factory()->for($resident)->create([
        'desk_id' => $desk->id,
        'date_start' => $day->toDateString(),
        'period' => Period::FullDay->value,
    ]);
    $external = User::factory()->create();
    DeskOccupation::factory()->create([
        'desk_id' => $desk->id,
        'user_id' => $external->id,
        'date' => $day->toDateString(),
        'period' => Period::Morning->value,
    ]);

    $entry = occupancyService()->forDate($day)['floors'][1]['assigned'][0];

    expect($entry['status'])->toBe('absent')
        ->and($entry['occupations'])->toHaveCount(1)
        ->and($entry['occupations']->first()->user_id)->toBe($external->id);
});

it('liste les résas salles du jour chronologiquement (annulées et autres jours exclues)', function () {
    $day = occupancyWorkingDay();

    $afternoon = Booking::factory()->create([
        'starts_at' => $day->setTime(14, 0),
        'ends_at' => $day->setTime(15, 0),
    ]);
    $morning = Booking::factory()->create([
        'starts_at' => $day->setTime(9, 0),
        'ends_at' => $day->setTime(10, 0),
    ]);
    Booking::factory()->cancelled()->create([
        'starts_at' => $day->setTime(11, 0),
        'ends_at' => $day->setTime(12, 0),
    ]);
    Booking::factory()->create([
        'starts_at' => $day->addWeek()->setTime(9, 0),
        'ends_at' => $day->addWeek()->setTime(10, 0),
    ]);

    $bookings = occupancyService()->forDate($day)['room_bookings'];

    expect($bookings->pluck('id')->all())->toBe([$morning->id, $afternoon->id]);
});

// --- Anti-régression N+1 (finding M8) ---------------------------------------

it('garde un nombre de requêtes CONSTANT quelle que soit la taille du parc', function () {
    $day = occupancyWorkingDay();

    $seed = function (int $assigned, int $unassigned) use ($day): void {
        foreach (range(1, $assigned) as $i) {
            [$desk, $user] = residentDesk(floor: ($i % 2) + 1);
            DeskAbsence::factory()->for($user)->create([
                'desk_id' => $desk->id,
                'date_start' => $day->toDateString(),
                'recurrence_type' => DeskAbsenceRecurrence::Weekly->value,
                'recurrence_day_of_week' => $day->dayOfWeek,
                'period' => Period::FullDay->value,
            ]);
        }
        foreach (range(1, $unassigned) as $i) {
            $desk = Resource::factory()->desk()->create(['floor' => 1]);
            DeskOccupation::factory()->create([
                'desk_id' => $desk->id,
                'date' => $day->toDateString(),
                'period' => Period::Morning->value,
            ]);
        }
    };

    $countQueries = function () use ($day): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        occupancyService()->forDate($day);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $seed(4, 2);
    $small = $countQueries();

    $seed(12, 6); // triple le parc
    $large = $countQueries();

    // Un N+1 par bureau ferait exploser $large (≥ +12 requêtes).
    expect($large)->toBe($small)
        ->and($small)->toBeLessThanOrEqual(14);
});

it('presentGivenAbsences n\'exécute AUCUNE requête (absences préchargées)', function () {
    $day = occupancyWorkingDay();

    [$desk, $user] = residentDesk();
    DeskAbsence::factory()->for($user)->create([
        'desk_id' => $desk->id,
        'date_start' => $day->toDateString(),
        'period' => Period::FullDay->value,
    ]);

    $absences = $user->deskAbsences()->get()->toBase();
    $service = app(PresenceService::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $covered = $service->presentGivenAbsences($absences, $day);
    $onFreeDay = $service->presentGivenAbsences(collect(), $day);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0)
        ->and($covered)->toBeFalse()
        ->and($onFreeDay)->toBeTrue();
});

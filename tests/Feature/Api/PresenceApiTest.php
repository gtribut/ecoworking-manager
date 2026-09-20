<?php

declare(strict_types=1);

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Lot C — « Mon bureau & mes absences » (PRD §3.4.6) : bureau attitré exposé,
 * absences à venir par défaut, note admin, édition bornée et suppression
 * bornée au début de l'absence.
 *
 * Toutes les comparaisons de dates se font côté SQL (`CURRENT_DATE`) : une
 * ligne fraîchement écrite est relue décalée du fuseau (piège connu du dépôt),
 * donc `isPast()`/`isFuture()` en PHP mentiraient.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

/** Résident doté d'un bureau attitré (seul profil autorisé sur le module). */
function presenceResident(array $deskAttributes = []): array
{
    $desk = Resource::factory()->assignedResident()->create($deskAttributes);
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create(['desk_id' => $desk->id]);

    return [$user, $desk];
}

function presenceRange(): string
{
    $from = CarbonImmutable::today()->subMonth()->toDateString();
    $to = CarbonImmutable::today()->addMonths(3)->toDateString();

    return "from={$from}&to={$to}";
}

// --- GET /api/presence ----------------------------------------------------

it('expose le bureau attitré du membre (PRD §3.4.6)', function () {
    [$user, $desk] = presenceResident(['name' => 'Bureau 12', 'floor' => 2, 'svg_desk_id' => 'desk-12']);

    $this->actingAs($user)->getJson('/api/presence?'.presenceRange())
        ->assertOk()
        ->assertJsonPath('desk.id', $desk->id)
        ->assertJsonPath('desk.name', 'Bureau 12')
        ->assertJsonPath('desk.floor', 2)
        ->assertJsonPath('desk.svg_desk_id', 'desk-12');
});

it('ne renvoie par défaut que les absences à venir ou en cours', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();

    $past = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDays(10)->toDateString(),
        'date_end' => $today->subDays(5)->toDateString(),
    ]);
    $ongoing = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDays(2)->toDateString(),
        'date_end' => $today->addDays(2)->toDateString(),
    ]);
    $future = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->addDays(10)->toDateString(),
        'date_end' => null,
    ]);
    // Récurrence hebdo commencée et sans fin : toujours en cours.
    $endless = DeskAbsence::factory()->weekly()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subMonth()->toDateString(),
        'date_end' => null,
    ]);

    $ids = collect($this->actingAs($user)->getJson('/api/presence?'.presenceRange())
        ->assertOk()
        ->json('absences'))->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$ongoing->id, $future->id, $endless->id])
        ->and($ids)->not->toContain($past->id);
});

it('renvoie l\'historique complet avec ?all=1', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();

    DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDays(10)->toDateString(),
        'date_end' => $today->subDays(5)->toDateString(),
    ]);

    $this->actingAs($user)->getJson('/api/presence?'.presenceRange().'&all=1')
        ->assertOk()
        ->assertJsonCount(1, 'absences');
});

it('renvoie les droits d\'édition calculés en SQL (can_edit / can_delete)', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();

    // Commencée hier : la fenêtre d'action du membre est fermée.
    $started = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDay()->toDateString(),
        'date_end' => $today->addDays(3)->toDateString(),
    ]);
    // Commence aujourd'hui : encore modifiable ET supprimable (jour inclus).
    $startsToday = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->toDateString(),
        'date_end' => $today->addDays(3)->toDateString(),
    ]);

    $absences = collect($this->actingAs($user)->getJson('/api/presence?'.presenceRange())
        ->assertOk()
        ->json('absences'))->keyBy('id');

    expect($absences[$started->id]['can_edit'])->toBeFalse()
        ->and($absences[$started->id]['can_delete'])->toBeFalse()
        ->and($absences[$startsToday->id]['can_edit'])->toBeTrue()
        ->and($absences[$startsToday->id]['can_delete'])->toBeTrue();
});

it('isole les absences : un membre ne voit jamais celles d\'un autre', function () {
    [$user, $desk] = presenceResident();
    [$other, $otherDesk] = presenceResident();

    DeskAbsence::factory()->create(['user_id' => $other->id, 'desk_id' => $otherDesk->id]);

    $this->actingAs($user)->getJson('/api/presence?'.presenceRange())
        ->assertOk()
        ->assertJsonCount(0, 'absences');
});

// --- POST /api/absences ---------------------------------------------------

it('accepte une note et une date de fin en récurrence hebdomadaire', function () {
    [$user] = presenceResident();
    $today = CarbonImmutable::today();

    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => $today->addDay()->toDateString(),
        'date_end' => $today->addMonths(2)->toDateString(),
        'period' => 'full_day',
        'recurrence_type' => 'weekly',
        'recurrence_day_of_week' => 5,
        'notes' => 'Télétravail le vendredi',
    ])->assertCreated()
        ->assertJsonPath('data.notes', 'Télétravail le vendredi')
        ->assertJsonPath('data.date_end', $today->addMonths(2)->toDateString());

    $absence = DeskAbsence::query()->where('user_id', $user->id)->sole();

    expect($absence->recurrence_type)->toBe(DeskAbsenceRecurrence::Weekly)
        ->and($absence->recurrence_day_of_week)->toBe(5)
        ->and($absence->date_end?->toDateString())->toBe($today->addMonths(2)->toDateString())
        ->and($absence->notes)->toBe('Télétravail le vendredi');
});

it('refuse une note trop longue (422)', function () {
    [$user] = presenceResident();

    $this->actingAs($user)->postJson('/api/absences', [
        'date_start' => CarbonImmutable::today()->addDay()->toDateString(),
        'notes' => str_repeat('a', 256),
    ])->assertStatus(422)->assertJsonValidationErrors('notes');
});

// --- PATCH /api/absences/{absence} ---------------------------------------

it('modifie sa propre absence non commencée', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->addDays(5)->toDateString(),
        'date_end' => null,
        'period' => Period::FullDay->value,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => $today->addDays(6)->toDateString(),
        'date_end' => $today->addDays(8)->toDateString(),
        'period' => 'morning',
        'recurrence_type' => 'none',
        'notes' => 'Formation',
    ])->assertOk()
        ->assertJsonPath('data.period', Period::Morning->value)
        ->assertJsonPath('data.notes', 'Formation');

    $absence->refresh();

    expect($absence->date_start->toDateString())->toBe($today->addDays(6)->toDateString())
        ->and($absence->date_end?->toDateString())->toBe($today->addDays(8)->toDateString())
        ->and($absence->period)->toBe(Period::Morning);
});

it('refuse la modification d\'une absence déjà commencée (403, comparaison SQL)', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDay()->toDateString(), // commencée hier
        'date_end' => $today->addDays(3)->toDateString(),
    ]);

    $this->actingAs($user)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => $today->addDays(4)->toDateString(),
    ])->assertForbidden();
});

it('aligne la fenêtre de modification sur celle de suppression (jour de début inclus)', function () {
    // Sans cet alignement, une absence commençant aujourd'hui serait
    // supprimable puis re-déclarable : contournement de la borne d'édition.
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->toDateString(),
        'date_end' => null,
    ]);

    $this->actingAs($user)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => $today->addDay()->toDateString(),
        'period' => 'morning',
    ])->assertOk();
});

it('refuse de modifier l\'absence d\'un autre membre (403)', function () {
    [$owner, $desk] = presenceResident();
    [$intruder] = presenceResident();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $owner->id, 'desk_id' => $desk->id,
        'date_start' => CarbonImmutable::today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($intruder)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => CarbonImmutable::today()->addDays(6)->toDateString(),
    ])->assertForbidden();
});

it('ne notifie pas les admins lors d\'une modification (seule la déclaration notifie)', function () {
    Notification::fake();
    [$user, $desk] = presenceResident();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => CarbonImmutable::today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($user)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => CarbonImmutable::today()->addDays(6)->toDateString(),
    ])->assertOk();

    Notification::assertNothingSent();
});

// --- DELETE /api/absences/{absence} --------------------------------------

it('supprime une absence jusqu\'au jour de son début inclus', function () {
    [$user, $desk] = presenceResident();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => CarbonImmutable::today()->toDateString(),
    ]);

    $this->actingAs($user)->deleteJson("/api/absences/{$absence->id}")->assertOk();

    expect(DeskAbsence::query()->whereKey($absence->id)->exists())->toBeFalse();
});

it('refuse au membre la suppression d\'une absence passée (403, audit admin uniquement)', function () {
    [$user, $desk] = presenceResident();
    $today = CarbonImmutable::today();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->subDays(3)->toDateString(),
        'date_end' => $today->subDay()->toDateString(),
    ]);

    $this->actingAs($user)->deleteJson("/api/absences/{$absence->id}")->assertForbidden();

    expect(DeskAbsence::query()->whereKey($absence->id)->exists())->toBeTrue();
});

// --- Note de l'absence ----------------------------------------------------

it('renvoie la note au titulaire quel que soit l\'auteur de la saisie', function () {
    // Tranché 2026-09-20 (recette) : pas de note « interne » vs « membre »,
    // c'est une simple description partagée entre le titulaire et Ecoworking.
    [$user, $desk] = presenceResident();
    $admin = User::factory()->admin()->create();
    $today = CarbonImmutable::today();

    $mine = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->addDays(2)->toDateString(),
        'notes' => 'Déplacement client',
        'created_by' => $user->id,
    ]);
    $fromAdmin = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->addDays(4)->toDateString(),
        'notes' => 'Absence signalée par téléphone',
        'created_by' => $admin->id,
    ]);
    // Ligne sans auteur (import, seed) : traitée comme les autres.
    $orphan = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => $today->addDays(6)->toDateString(),
        'notes' => 'Congés',
        'created_by' => null,
    ]);

    $response = $this->actingAs($user)->getJson('/api/presence?'.presenceRange())->assertOk();
    $absences = collect($response->json('absences'))->keyBy('id');

    expect($absences[$mine->id]['notes'])->toBe('Déplacement client')
        ->and($absences[$fromAdmin->id]['notes'])->toBe('Absence signalée par téléphone')
        ->and($absences[$orphan->id]['notes'])->toBe('Congés');
});

it('laisse le titulaire modifier la note d\'une absence saisie par l\'accueil', function () {
    [$user, $desk] = presenceResident();
    $admin = User::factory()->admin()->create();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $user->id, 'desk_id' => $desk->id,
        'date_start' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'notes' => 'Signalée par téléphone',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($user)->patchJson("/api/absences/{$absence->id}", [
        'date_start' => CarbonImmutable::today()->addDays(4)->toDateString(),
        'notes' => 'Congés posés',
    ])->assertOk()->assertJsonPath('data.notes', 'Congés posés');

    expect($absence->fresh()->notes)->toBe('Congés posés');
});

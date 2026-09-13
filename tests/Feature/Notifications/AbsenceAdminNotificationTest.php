<?php

declare(strict_types=1);

use App\Enums\Period;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\AbsenceDeclaredNotification;
use App\Notifications\AbsenceRecordedNotification;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Lot G — « absence enregistrée par l'admin pour le résident » (PRD §3.8.4).
 * In-app seul. Même garde que les réservations : rien ne part vers le membre
 * qui agit lui-même (la notification Q25 vers les admins, elle, est inchangée).
 */

/** Résident doté d'un bureau attitré (prérequis du module présence). */
function notifiedResident(): User
{
    $resident = User::factory()->resident()->create();
    MemberProfile::factory()->for($resident)->create([
        'desk_id' => Resource::factory()->desk()->create()->id,
    ]);

    return $resident;
}

// ── Absence enregistrée par l'admin ─────────────────────────────────────────

it('notifie le résident quand l\'admin enregistre une absence pour lui', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $resident = notifiedResident();

    $this->actingAs($admin);
    app(PresenceService::class)->declareAbsence([
        'user' => $resident,
        'date_start' => CarbonImmutable::tomorrow(),
        'period' => Period::FullDay,
        'created_by' => $admin->id,
    ]);

    Notification::assertSentTo($resident, AbsenceRecordedNotification::class, function ($notification, array $channels) {
        return $channels === ['database'];
    });
});

it('notifie la modification puis la suppression d\'une absence par l\'admin', function () {
    $admin = User::factory()->admin()->create();
    $resident = notifiedResident();
    $absence = DeskAbsence::factory()->create([
        'user_id' => $resident->id,
        'desk_id' => $resident->memberProfile->desk_id,
    ]);

    Notification::fake();

    $this->actingAs($admin);
    app(PresenceService::class)->updateAbsence($absence, [
        'date_start' => CarbonImmutable::tomorrow()->addWeek(),
        'period' => Period::Morning,
    ]);
    $absence->delete();

    Notification::assertSentToTimes($resident, AbsenceRecordedNotification::class, 2);
});

it('ne notifie pas le résident qui déclare lui-même son absence', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $resident = notifiedResident();

    $this->actingAs($resident)->postJson('/api/absences', [
        'date_start' => now()->addDay()->toDateString(),
        'period' => Period::FullDay->value,
    ])->assertCreated();

    // Seule la notification Q25 vers les admins part (inchangée par le lot G).
    Notification::assertNotSentTo($resident, AbsenceRecordedNotification::class);
    Notification::assertSentTo($admin, AbsenceDeclaredNotification::class);
});

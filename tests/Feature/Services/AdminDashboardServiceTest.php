<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\MemberDocumentValidation;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminDashboardService;
use Carbon\CarbonImmutable;

/**
 * C12.6 — logique du dashboard admin (PRD §4.1.2). Les widgets Filament sont
 * minces : c'est CE service qui porte (et verrouille) le comportement.
 */
function dashboardService(): AdminDashboardService
{
    return app(AdminDashboardService::class);
}

// --- KPIs -----------------------------------------------------------------

it('compte uniquement les membres actifs', function () {
    MemberProfile::factory()->count(2)->create();
    MemberProfile::factory()->paused()->create();

    expect(dashboardService()->activeMembersCount())->toBe(2);
});

it('compte uniquement les abonnements actifs', function () {
    Subscription::factory()->create();
    Subscription::factory()->paused()->create();
    Subscription::factory()->ended()->create();

    expect(dashboardService()->activeSubscriptionsCount())->toBe(1);
});

it('calcule les factures en retard : count + montant restant dû', function () {
    // En retard : émise, échue, partiellement payée.
    Invoice::factory()->issued()->create([
        'due_at' => now()->subDays(5)->toDateString(),
        'total_ttc' => '100.00',
        'amount_paid' => '25.00',
    ]);
    // Exclues : soldée, brouillon, avoir, échéance future.
    Invoice::factory()->paid()->create([
        'due_at' => now()->subDays(5)->toDateString(),
        'total_ttc' => '80.00',
        'amount_paid' => '80.00',
    ]);
    Invoice::factory()->create(['total_ttc' => '999.00']);
    Invoice::factory()->issued()->creditNote()->create([
        'due_at' => now()->subDays(5)->toDateString(),
        'total_ttc' => '-40.00',
    ]);
    Invoice::factory()->issued()->create([
        'due_at' => now()->addDays(5)->toDateString(),
        'total_ttc' => '60.00',
    ]);

    expect(dashboardService()->overdueInvoicesStats())->toBe([
        'count' => 1,
        'amount_due' => 75.0,
    ]);
});

it('compte en retard une facture `sent` échue même si le cron overdue n\'est pas passé', function () {
    $invoice = Invoice::factory()->issued()->create([
        'due_at' => now()->subDay()->toDateString(),
        'total_ttc' => '50.00',
        'amount_paid' => '0.00',
    ]);

    expect($invoice->status->value)->toBe('sent')
        ->and(dashboardService()->overdueInvoicesStats()['count'])->toBe(1);
});

it('calcule le CA émis du mois (avoirs déduits, brouillons et autres mois exclus)', function () {
    Invoice::factory()->issued()->create([
        'issued_at' => now()->toDateString(),
        'total_ttc' => '100.00',
    ]);
    Invoice::factory()->issued()->creditNote()->create([
        'issued_at' => now()->toDateString(),
        'total_ttc' => '-40.00',
    ]);
    Invoice::factory()->create(['total_ttc' => '999.00']); // brouillon, sans numéro
    Invoice::factory()->issued()->create([
        'issued_at' => now()->subMonthNoOverflow()->toDateString(),
        'total_ttc' => '50.00',
    ]);

    $service = dashboardService();

    expect($service->issuedRevenueForMonth(now()))->toBe(60.0)
        ->and($service->issuedRevenueForMonth(now()->subMonthNoOverflow()))->toBe(50.0);
});

it('calcule le taux d\'occupation des salles sur la semaine', function () {
    // Semaine du 7 au 13 septembre 2026 : 5 jours ouvrés, aucun férié.
    $week = CarbonImmutable::parse('2026-09-09');
    $room = Resource::factory()->meetingRoom()->create();

    // 3 h confirmées dans la semaine → 3 / (1 salle × 5 j × 10 h) = 6 %.
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => '2026-09-08 09:00:00',
        'ends_at' => '2026-09-08 12:00:00',
    ]);
    // Exclues : annulée, et hors semaine.
    Booking::factory()->cancelled()->create([
        'resource_id' => $room->id,
        'starts_at' => '2026-09-08 14:00:00',
        'ends_at' => '2026-09-08 16:00:00',
    ]);
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => '2026-09-01 09:00:00',
        'ends_at' => '2026-09-01 12:00:00',
    ]);

    expect(dashboardService()->roomOccupancyRateForWeek($week))->toBe(6.0);
});

it('retourne 0 % d\'occupation sans salle active (pas de division par zéro)', function () {
    expect(dashboardService()->roomOccupancyRateForWeek(now()))->toBe(0.0);
});

// --- Alertes ----------------------------------------------------------------

it('liste les abonnements actifs se terminant sous 30 jours, chronologiquement', function () {
    $soon = Subscription::factory()->create(['ends_at' => now()->addDays(10)->toDateString()]);
    $sooner = Subscription::factory()->create(['ends_at' => now()->addDays(3)->toDateString()]);
    Subscription::factory()->create(['ends_at' => now()->addDays(45)->toDateString()]);
    Subscription::factory()->create(['ends_at' => null]);
    Subscription::factory()->create([
        'status' => SubscriptionStatus::Ended->value,
        'ends_at' => now()->addDays(5)->toDateString(),
    ]);

    $ending = dashboardService()->endingSubscriptionsQuery()->get();

    expect($ending->pluck('id')->all())->toBe([$sooner->id, $soon->id]);
});

it('calcule le pourcentage de membres actifs ayant validé chaque document interne', function () {
    $document = InternalDocument::factory()->create(['version' => '2.0']);
    InternalDocument::factory()->create(['is_active' => false]);
    InternalDocument::factory()->create(['published_at' => null]);

    $validated = MemberProfile::factory()->create();
    $notValidated = MemberProfile::factory()->create();
    $pausedMember = MemberProfile::factory()->paused()->create();

    // Comptée : version courante, membre actif.
    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $document->id,
        'user_id' => $validated->user_id,
        'version' => '2.0',
    ]);
    // Exclues : ancienne version, membre non actif.
    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $document->id,
        'user_id' => $notValidated->user_id,
        'version' => '1.0',
    ]);
    MemberDocumentValidation::factory()->create([
        'internal_document_id' => $document->id,
        'user_id' => $pausedMember->user_id,
        'version' => '2.0',
    ]);

    $stats = dashboardService()->internalDocumentValidationStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]['document']->id)->toBe($document->id)
        ->and($stats[0]['validated'])->toBe(1)
        ->and($stats[0]['total'])->toBe(2)
        ->and($stats[0]['rate'])->toBe(50.0);
});

// --- Vue « aujourd'hui » & activité récente ---------------------------------

it('liste les nouveaux membres arrivés cette semaine', function () {
    $thisWeek = MemberProfile::factory()->create(['arrival_date' => now()->toDateString()]);
    MemberProfile::factory()->create(['arrival_date' => now()->subDays(30)->toDateString()]);

    $arrivals = dashboardService()->newMembersThisWeekQuery()->get();

    expect($arrivals->pluck('id')->all())->toBe([$thisWeek->id]);
});

it('liste les absences déclarées depuis le portail, la plus récente en premier', function () {
    $member = User::factory()->resident()->create();
    $admin = User::factory()->admin()->create();

    $old = DeskAbsence::factory()->create(['user_id' => $member->id, 'created_by' => $member->id]);
    $old->forceFill(['created_at' => CarbonImmutable::now()->subDays(3)])->saveQuietly();
    $recent = DeskAbsence::factory()->create(['user_id' => $member->id, 'created_by' => $member->id]);

    // Saisie par l'accueil : l'admin n'a pas à être alerté de sa propre action.
    DeskAbsence::factory()->create(['user_id' => $member->id, 'created_by' => $admin->id]);
    // Hors fenêtre de 14 jours.
    $stale = DeskAbsence::factory()->create(['user_id' => $member->id, 'created_by' => $member->id]);
    $stale->forceFill(['created_at' => CarbonImmutable::now()->subDays(20)])->saveQuietly();

    $absences = dashboardService()->recentPortalAbsencesQuery()->get();

    expect($absences->pluck('id')->all())->toBe([$recent->id, $old->id]);
});

it('retourne les 10 dernières entrées d\'audit log, la plus récente en premier', function () {
    foreach (range(1, 12) as $i) {
        activity()->log("entrée n°{$i}");
    }

    $activities = dashboardService()->recentActivityQuery()->get();

    expect($activities)->toHaveCount(10)
        ->and($activities->first()->description)->toBe('entrée n°12');
});

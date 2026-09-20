<?php

declare(strict_types=1);

use App\Filament\Widgets\DashboardKpisWidget;
use App\Filament\Widgets\DeclaredAbsencesWidget;
use App\Filament\Widgets\EndingSubscriptionsWidget;
use App\Filament\Widgets\InternalDocumentValidationsWidget;
use App\Filament\Widgets\NewMembersWidget;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\TodayBookingsWidget;
use App\Filament\Widgets\TodayDeskOccupationsWidget;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Offer;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.6 — widgets du dashboard admin (PRD §4.1.2). Rendu testé au niveau
 * composant Livewire (le 2FA obligatoire casse l'accès HTTP). La logique
 * métier est verrouillée dans AdminDashboardServiceTest / DailyOccupancyServiceTest.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

it('enregistre tous les widgets du dashboard sur le panel admin', function () {
    $widgets = collect(Filament::getPanel('admin')->getWidgets())
        ->map(fn (string|WidgetConfiguration $widget): string => $widget instanceof WidgetConfiguration
            ? $widget->widget
            : $widget);

    expect($widgets)
        ->toContain(DashboardKpisWidget::class)
        ->toContain(OverdueInvoicesWidget::class)
        ->toContain(EndingSubscriptionsWidget::class)
        ->toContain(InternalDocumentValidationsWidget::class)
        ->toContain(TodayBookingsWidget::class)
        ->toContain(TodayDeskOccupationsWidget::class)
        ->toContain(NewMembersWidget::class)
        ->toContain(DeclaredAbsencesWidget::class)
        ->toContain(RecentActivityWidget::class);
});

it('rend les KPIs avec les compteurs du service', function () {
    MemberProfile::factory()->count(3)->create();
    Invoice::factory()->issued()->create([
        'due_at' => now()->subDays(3)->toDateString(),
        'total_ttc' => '120.00',
        'amount_paid' => '0.00',
    ]);

    Livewire::test(DashboardKpisWidget::class)
        ->assertOk()
        ->assertSee('Membres actifs')
        ->assertSee('Abonnements actifs')
        ->assertSee('Factures en retard')
        ->assertSee('CA du mois (émis)')
        ->assertSee('Occupation salles (semaine)')
        ->assertSee('3');
});

it('affiche le top 5 des factures en retard (les non-échues sont exclues)', function () {
    $overdue = Invoice::factory()->issued()->create([
        'due_at' => now()->subDays(3)->toDateString(),
        'total_ttc' => '120.00',
        'amount_paid' => '0.00',
    ]);
    $current = Invoice::factory()->issued()->create([
        'due_at' => now()->addDays(10)->toDateString(),
        'total_ttc' => '80.00',
    ]);

    Livewire::test(OverdueInvoicesWidget::class)
        ->assertOk()
        ->assertSee($overdue->number)
        ->assertDontSee($current->number);
});

it('affiche les abonnements se terminant sous 30 jours', function () {
    $offer = Offer::factory()->subscription()->create(['name' => 'Résident temps plein']);
    Subscription::factory()->create([
        'offer_id' => $offer->id,
        'ends_at' => now()->addDays(10)->toDateString(),
    ]);

    Livewire::test(EndingSubscriptionsWidget::class)
        ->assertOk()
        ->assertSee('Résident temps plein');
});

it('affiche le taux de validation des documents internes', function () {
    InternalDocument::factory()->create(['title' => 'Règlement intérieur', 'version' => '3.1']);
    MemberProfile::factory()->create();

    Livewire::test(InternalDocumentValidationsWidget::class)
        ->assertOk()
        ->assertSee('Règlement intérieur')
        ->assertSee('0 / 1 membre(s) actif(s) ont validé');
});

it('affiche les résas salles du jour', function () {
    $room = Resource::factory()->meetingRoom()->create(['name' => 'Salle Rhône']);
    Booking::factory()->create([
        'resource_id' => $room->id,
        'starts_at' => now()->setTime(10, 0),
        'ends_at' => now()->setTime(11, 0),
    ]);

    Livewire::test(TodayBookingsWidget::class)
        ->assertOk()
        ->assertSee('Salle Rhône');
});

it('affiche les bureaux nomades du jour', function () {
    $desk = Resource::factory()->desk()->create(['name' => 'Bureau nomade 7']);
    DeskOccupation::factory()->create([
        'desk_id' => $desk->id,
        'date' => now()->toDateString(),
    ]);

    Livewire::test(TodayDeskOccupationsWidget::class)
        ->assertOk()
        ->assertSee('Bureau nomade 7');
});

it('affiche les nouveaux membres de la semaine', function () {
    $user = User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Verne']);
    MemberProfile::factory()->for($user)->create(['arrival_date' => now()->toDateString()]);

    Livewire::test(NewMembersWidget::class)
        ->assertOk()
        ->assertSee('Camille Verne');
});

it('affiche les absences déclarées depuis le portail (surface de Q25)', function () {
    $member = User::factory()->resident()->create(['first_name' => 'Inès', 'last_name' => 'Rahmani']);
    DeskAbsence::factory()->create(['user_id' => $member->id, 'created_by' => $member->id]);

    Livewire::test(DeclaredAbsencesWidget::class)
        ->assertOk()
        ->assertSee('Inès Rahmani');
});

it('affiche l\'activité récente issue de l\'audit log', function () {
    activity()->log('Entrée de test audit');

    Livewire::test(RecentActivityWidget::class)
        ->assertOk()
        ->assertSee('Entrée de test audit');
});

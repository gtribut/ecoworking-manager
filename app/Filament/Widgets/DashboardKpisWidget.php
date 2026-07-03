<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * KPIs du dashboard admin (C12.6, PRD §4.1.2) : membres actifs, abonnements
 * actifs, factures en retard, CA du mois (vs précédent), taux d'occupation
 * salles. Widget MINCE — tout le calcul vit dans AdminDashboardService.
 */
class DashboardKpisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $service = app(AdminDashboardService::class);
        $today = CarbonImmutable::today();

        $overdue = $service->overdueInvoicesStats();
        $currentRevenue = $service->issuedRevenueForMonth($today);
        $previousRevenue = $service->issuedRevenueForMonth($today->subMonthNoOverflow());

        return [
            Stat::make('Membres actifs', (string) $service->activeMembersCount()),
            Stat::make('Abonnements actifs', (string) $service->activeSubscriptionsCount()),
            Stat::make('Factures en retard', (string) $overdue['count'])
                ->description(Number::currency($overdue['amount_due'], in: 'EUR', locale: 'fr').' impayés')
                ->color($overdue['count'] > 0 ? 'danger' : 'success'),
            Stat::make('CA du mois (émis)', Number::currency($currentRevenue, in: 'EUR', locale: 'fr'))
                ->description('Mois précédent : '.Number::currency($previousRevenue, in: 'EUR', locale: 'fr'))
                ->color($currentRevenue >= $previousRevenue ? 'success' : 'warning'),
            Stat::make(
                'Occupation salles (semaine)',
                Number::percentage($service->roomOccupancyRateForWeek($today), maxPrecision: 1, locale: 'fr'),
            ),
        ];
    }
}

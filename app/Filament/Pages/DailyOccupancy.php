<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DailyOccupancyService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Page « Occupation du jour » (C12.6, PRD §4.8.4) : synthèse quotidienne par
 * étage — bureaux attitrés (présent/absent/pas d'info), bureaux libres
 * (external ou disponible), capacité restante, résas salles.
 *
 * Page MINCE : toutes les données viennent de DailyOccupancyService (testé),
 * en nombre constant de requêtes (finding perf M8).
 */
class DailyOccupancy extends Page
{
    protected string $view = 'filament.pages.daily-occupancy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Espaces & réservations';

    protected static ?string $navigationLabel = 'Occupation du jour';

    protected static ?string $title = 'Occupation du jour';

    protected static ?int $navigationSort = 0;

    /** Date affichée (Y-m-d) — aujourd'hui par défaut, modifiable (PRD §4.8.4). */
    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    /** Back-office admin uniquement (défense en profondeur, en plus de canAccessPanel). */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'occupancy' => app(DailyOccupancyService::class)->forDate($this->parsedDate()),
        ];
    }

    /** Tolère une saisie invalide/vidée du champ date (retombe sur aujourd'hui). */
    private function parsedDate(): CarbonImmutable
    {
        return rescue(
            fn (): CarbonImmutable => CarbonImmutable::createFromFormat('!Y-m-d', $this->date),
            CarbonImmutable::today(),
            report: false,
        );
    }
}

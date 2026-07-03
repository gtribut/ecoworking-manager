<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\MemberProfile;
use App\Services\AdminDashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Vue rapide « Aujourd'hui » — nouveaux membres arrivés cette semaine
 * (C12.6, PRD §4.1.2). Widget MINCE : requête dans AdminDashboardService.
 */
class NewMembersWidget extends TableWidget
{
    protected static ?int $sort = 7;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nouveaux membres cette semaine')
            ->query(fn () => app(AdminDashboardService::class)->newMembersThisWeekQuery())
            ->paginated(false)
            ->emptyStateHeading('Aucune arrivée cette semaine')
            ->columns([
                TextColumn::make('user')
                    ->label('Membre')
                    ->getStateUsing(fn (MemberProfile $record): string => $record->user?->fullName() ?? '—'),
                TextColumn::make('company.name')
                    ->label('Entreprise')
                    ->placeholder('—'),
                TextColumn::make('arrival_date')
                    ->label('Arrivé le')
                    ->date('d/m/Y'),
            ]);
    }
}

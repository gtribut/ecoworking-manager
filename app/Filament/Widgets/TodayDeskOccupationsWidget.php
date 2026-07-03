<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\DeskOccupation;
use App\Services\DailyOccupancyService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Vue rapide « Aujourd'hui » — bureaux nomades (external) du jour (C12.6,
 * PRD §4.1.2). Widget MINCE : requête dans DailyOccupancyService.
 */
class TodayDeskOccupationsWidget extends TableWidget
{
    protected static ?int $sort = 6;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Bureaux nomades du jour')
            ->query(fn () => app(DailyOccupancyService::class)->externalOccupationsQuery(now()))
            ->paginated(false)
            ->emptyStateHeading('Aucun bureau nomade réservé aujourd\'hui')
            ->columns([
                TextColumn::make('desk.name')
                    ->label('Bureau'),
                TextColumn::make('period')
                    ->label('Créneau')
                    ->badge(),
                TextColumn::make('user')
                    ->label('Qui')
                    ->getStateUsing(fn (DeskOccupation $record): string => $record->user?->fullName() ?? '—'),
            ]);
    }
}

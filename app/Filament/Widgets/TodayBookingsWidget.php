<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Services\DailyOccupancyService;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Vue rapide « Aujourd'hui » — réservations de salles du jour (C12.6,
 * PRD §4.1.2). Widget MINCE : requête dans DailyOccupancyService.
 */
class TodayBookingsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Réservations salles du jour')
            ->query(fn () => app(DailyOccupancyService::class)->roomBookingsQuery(now()))
            ->paginated(false)
            ->emptyStateHeading('Aucune réservation de salle aujourd\'hui')
            ->columns([
                TextColumn::make('resource.name')
                    ->label('Salle'),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->dateTime('H:i'),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('H:i'),
                TextColumn::make('user')
                    ->label('Qui')
                    ->getStateUsing(fn (Booking $record): string => $record->user?->fullName() ?? '—'),
                TextColumn::make('title')
                    ->label('Libellé')
                    ->placeholder('—')
                    ->limit(30),
                IconColumn::make('ticket_id')
                    ->label('Ticket')
                    ->boolean()
                    ->getStateUsing(fn (Booking $record): bool => $record->ticket_id !== null),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Spatie\Activitylog\Models\Activity;

/**
 * « Activité récente » : 10 dernières entrées de l'audit log (C12.6,
 * PRD §4.1.2). Lecture seule — la Resource complète d'audit log est prévue
 * en C12.8b. Widget MINCE : requête dans AdminDashboardService.
 */
class RecentActivityWidget extends TableWidget
{
    protected static ?int $sort = 8;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Activité récente')
            ->query(fn () => app(AdminDashboardService::class)->recentActivityQuery())
            ->paginated(false)
            ->emptyStateHeading('Aucune activité enregistrée')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('description')
                    ->label('Événement'),
                TextColumn::make('subject_type')
                    ->label('Sujet')
                    ->getStateUsing(fn (Activity $record): string => $record->subject_type !== null
                        ? "{$record->subject_type} #{$record->subject_id}"
                        : '—'),
                TextColumn::make('causer')
                    ->label('Par')
                    ->getStateUsing(fn (Activity $record): string => $record->causer instanceof User
                        ? $record->causer->fullName()
                        : '—'),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Services\AdminDashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Bloc « Absences déclarées par les membres » (PRD Q25, tranché 2026-09-20).
 *
 * Une `AbsenceDeclaredNotification` part vers les admins à chaque déclaration
 * faite depuis le portail, mais le back-office n'a pas de cloche de
 * notifications (choix assumé : un seul type d'événement admin dans le MVP,
 * la plomberie Filament ne se justifiait pas). Ce widget est donc la surface
 * de restitution de Q25 : l'admin voit les bureaux libérés sans aller les
 * chercher dans `DeskAbsences`.
 *
 * Widget MINCE : la requête vit dans AdminDashboardService (CLAUDE.md §7).
 */
class DeclaredAbsencesWidget extends TableWidget
{
    protected static ?int $sort = 8;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Absences déclarées par les membres')
            ->description('Déclarations faites depuis le portail ces 14 derniers jours.')
            ->query(fn () => app(AdminDashboardService::class)->recentPortalAbsencesQuery())
            ->paginated(false)
            ->emptyStateHeading('Aucune absence déclarée récemment')
            ->recordUrl(fn (DeskAbsence $record): string => DeskAbsenceResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('user')
                    ->label('Membre')
                    ->getStateUsing(fn (DeskAbsence $record): string => $record->user?->fullName() ?? '—'),
                TextColumn::make('desk.name')
                    ->label('Bureau')
                    ->placeholder('—'),
                TextColumn::make('date_start')
                    ->label('Du')
                    ->date('d/m/Y'),
                // Absence d'un jour : `date_end` est nul, on le dit plutôt que
                // de laisser une colonne vide.
                TextColumn::make('date_end')
                    ->label('Au')
                    ->date('d/m/Y')
                    ->placeholder('jour unique'),
                TextColumn::make('period')
                    ->label('Période'),
                TextColumn::make('created_at')
                    ->label('Déclarée le')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}

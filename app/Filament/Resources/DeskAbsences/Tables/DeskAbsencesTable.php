<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences\Tables;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listing des absences déclarées (PRD §4.8.2) : membre, bureau, fenêtre,
 * période, récurrence, note. Filtres « à venir / passées » et bureau.
 */
class DeskAbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['desk', 'user']))
            ->columns([
                TextColumn::make('user.last_name')
                    ->label('Membre')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->weight('medium'),
                TextColumn::make('desk.name')
                    ->label('Bureau')
                    ->searchable(),
                TextColumn::make('date_start')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('date_end')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Période')
                    ->badge(),
                TextColumn::make('recurrence_type')
                    ->label('Récurrence')
                    ->badge()
                    ->formatStateUsing(fn (DeskAbsenceRecurrence $state, $record): string => $state === DeskAbsenceRecurrence::Weekly
                        ? 'Chaque '.mb_strtolower(self::weekday($record->recurrence_day_of_week))
                        : $state->getLabel()),
                TextColumn::make('notes')
                    ->label('Note')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                // Filtre TERNAIRE (et non deux cases indépendantes : cochées
                // ensemble, elles donnaient une liste vide). Comparaisons CÔTÉ
                // SQL via les scopes du modèle — jamais `isPast()` en PHP sur
                // une ligne fraîche (piège fuseau du dépôt).
                SelectFilter::make('period_status')
                    ->label('Période')
                    ->options([
                        'upcoming' => 'À venir ou en cours',
                        'past' => 'Terminées',
                    ])
                    ->default('upcoming')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'upcoming' => $query->upcoming(),
                        'past' => $query->whereNot(fn (Builder $ongoing) => $ongoing->upcoming()),
                        default => $query, // « Toutes » (option vide du select)
                    }),
                SelectFilter::make('desk')
                    ->label('Bureau')
                    ->relationship('desk', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('period')
                    ->label('Créneau')
                    ->options(Period::class),
                SelectFilter::make('recurrence_type')
                    ->label('Récurrence')
                    ->options(DeskAbsenceRecurrence::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date_start', 'desc');
    }

    private static function weekday(?int $day): string
    {
        return [
            0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi',
            4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi',
        ][$day] ?? 'semaine';
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Tables;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeskOccupationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['desk', 'user']))
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Créneau')
                    ->badge(),
                TextColumn::make('desk.name')
                    ->label('Bureau')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('user.last_name')
                    ->label('Occupant')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name']),
                TextColumn::make('source')
                    ->label('Origine')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Créneau')
                    ->options(Period::class),
                SelectFilter::make('source')
                    ->label('Origine')
                    ->options(DeskOccupationSource::class),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(DeskOccupationStatus::class),
                SelectFilter::make('desk')
                    ->label('Bureau')
                    ->relationship('desk', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}

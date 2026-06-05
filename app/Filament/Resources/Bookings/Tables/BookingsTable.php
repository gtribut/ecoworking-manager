<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['resource', 'user', 'billable']))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('resource.name')
                    ->label('Salle')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('user.last_name')
                    ->label('Membre')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name']),
                TextColumn::make('title')
                    ->label('Objet')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('is_internal')
                    ->label('Interne')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('price_ht')
                    ->label('Prix HT')
                    ->money('EUR')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('billable')
                    ->label('Facturé à')
                    ->getStateUsing(fn ($record): string => match (true) {
                        $record->billable instanceof User => $record->billable->fullName(),
                        $record->billable instanceof Company => $record->billable->name,
                        default => '—',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(BookingStatus::class),
                SelectFilter::make('resource')
                    ->label('Salle')
                    ->relationship('resource', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_internal')
                    ->label('Interne'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}

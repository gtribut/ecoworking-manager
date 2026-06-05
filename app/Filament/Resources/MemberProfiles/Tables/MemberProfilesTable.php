<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberProfiles\Tables;

use App\Enums\MemberProfileStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MemberProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.last_name')
                    ->label('Membre')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('company.name')
                    ->label('Entité')
                    ->getStateUsing(fn ($record): ?string => $record->company?->name)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('job_title')
                    ->label('Poste')
                    ->toggleable(),
                TextColumn::make('desk.name')
                    ->label('Bureau')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('show_in_directory')
                    ->label('Annuaire')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('arrival_date')
                    ->label('Arrivée')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(MemberProfileStatus::class),
                SelectFilter::make('company')
                    ->label('Entité')
                    ->relationship('company', 'legal_name')
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
            ]);
    }
}

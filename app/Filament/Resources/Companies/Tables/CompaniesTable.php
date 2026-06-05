<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Entité')
                    ->getStateUsing(fn ($record): string => $record->name)
                    // L'accesseur `name` n'est pas une colonne SQL : on recherche
                    // sur les champs sous-jacents (raison sociale / prénom / nom).
                    ->searchable(query: function ($query, string $search) {
                        return $query
                            ->where('legal_name', 'ilike', "%{$search}%")
                            ->orWhere('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    })
                    ->weight('medium'),
                TextColumn::make('entity_type')
                    ->label('Nature')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('city')
                    ->label('Ville')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('billing_email')
                    ->label('Email factu.')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('discount_rate')
                    ->label('Remise')
                    ->suffix(' %')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->label('Nature')
                    ->options(CompanyType::class),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(CompanyStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('legal_name');
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Resources\Tables;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ResourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('assignment')
                    ->label('Affectation')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('capacity')
                    ->label('Capacité')
                    ->numeric()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('floor')
                    ->label('Étage')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                IconColumn::make('is_out_of_service')
                    ->label('Hors service')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(ResourceType::class),
                SelectFilter::make('assignment')
                    ->label('Affectation')
                    ->options(ResourceAssignment::class),
                TernaryFilter::make('is_active')
                    ->label('Actif'),
                TernaryFilter::make('is_out_of_service')
                    ->label('Hors service'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order');
    }
}

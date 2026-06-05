<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdministrativeDocuments\Tables;

use App\Enums\AdministrativeDocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdministrativeDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('company'))
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('company.legal_name')
                    ->label('Entité')
                    ->formatStateUsing(fn ($record): string => $record->company?->name ?? '—')
                    ->searchable(['companies.legal_name', 'companies.first_name', 'companies.last_name']),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('document_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AdministrativeDocumentType::class),
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
            ])
            ->defaultSort('document_date', 'desc');
    }
}

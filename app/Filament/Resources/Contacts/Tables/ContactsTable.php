<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContactRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Contact')
                    ->formatStateUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('company.name')
                    ->label('Entité')
                    ->getStateUsing(fn ($record): ?string => $record->company?->name)
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->options(ContactRole::class),
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

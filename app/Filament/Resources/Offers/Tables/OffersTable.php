<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Tables;

use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('subscriber_kind')
                    ->label('Souscripteur')
                    ->toggleable(),
                TextColumn::make('unit_price_ht')
                    ->label('Prix HT')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('vat_rate')
                    ->label('TVA')
                    ->suffix(' %')
                    ->toggleable(),
                TextColumn::make('billing_period')
                    ->label('Périodicité')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label('Portail')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(OfferType::class),
                SelectFilter::make('subscriber_kind')
                    ->label('Souscripteur')
                    ->options(SubscriberKind::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
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

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsTable
{
    /** Libellé d'une partie polymorphe (User ou Company). */
    private static function partyLabel(?Model $party): string
    {
        return match (true) {
            $party instanceof User => $party->fullName(),
            $party instanceof Company => $party->name,
            default => '—',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['offer', 'subscriber', 'billable']))
            ->columns([
                TextColumn::make('offer.name')
                    ->label('Offre')
                    ->description(fn ($record): ?string => $record->offer?->code)
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('subscriber')
                    ->label('Souscripteur')
                    ->getStateUsing(fn ($record): string => self::partyLabel($record->subscriber)),
                TextColumn::make('billable')
                    ->label('Facturé à')
                    ->getStateUsing(fn ($record): string => self::partyLabel($record->billable))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('billing_day')
                    ->label('Jour factu.')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(SubscriptionStatus::class),
                SelectFilter::make('offer')
                    ->label('Offre')
                    ->relationship('offer', 'name')
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
            ->defaultSort('starts_at', 'desc');
    }
}

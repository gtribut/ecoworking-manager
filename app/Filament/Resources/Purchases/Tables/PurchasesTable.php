<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases\Tables;

use App\Enums\TicketType;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'offer', 'billable']))
            ->columns([
                TextColumn::make('purchased_at')
                    ->label('Acheté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.last_name')
                    ->label('Bénéficiaire')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->weight('medium'),
                TextColumn::make('label')
                    ->label('Libellé')
                    ->description(fn ($record): ?string => $record->offer?->code)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ticket_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Qté')
                    ->numeric(),
                TextColumn::make('unit_price_ht')
                    ->label('PU HT')
                    ->money('EUR')
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
                SelectFilter::make('ticket_type')
                    ->label('Type de ticket')
                    ->options(TicketType::class),
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
            ->defaultSort('purchased_at', 'desc');
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('invoice'))
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Encaissé le')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('invoice.number')
                    ->label('Facture')
                    ->placeholder('—')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Moyen')
                    ->badge(),
                TextColumn::make('reference')
                    ->label('Référence')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Moyen de paiement')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            // Pas de suppression en masse (cf. InvoicesTable) : la suppression
            // unitaire d'un paiement passe par sa page d'édition (Policy vérifiée).
            ->defaultSort('paid_at', 'desc');
    }
}

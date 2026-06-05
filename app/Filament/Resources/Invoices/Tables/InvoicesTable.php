<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('billable'))
            ->columns([
                TextColumn::make('number')
                    ->label('Numéro')
                    ->placeholder('Brouillon')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('billable')
                    ->label('Facturé à')
                    ->getStateUsing(fn ($record): string => match (true) {
                        $record->billable instanceof User => $record->billable->fullName(),
                        $record->billable instanceof Company => $record->billable->name,
                        default => '—',
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('is_credit_note')
                    ->label('Avoir')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label('Émise le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('total_ttc')
                    ->label('Total TTC')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Payé')
                    ->money('EUR')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(InvoiceStatus::class),
                TernaryFilter::make('is_credit_note')
                    ->label('Avoir'),
            ])
            ->recordActions([
                // Émise → consultation (figée) ; brouillon → édition. Filament masque
                // automatiquement chaque action selon la Policy (view / update).
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Seuls les brouillons sont supprimables (InvoicePolicy::delete).
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

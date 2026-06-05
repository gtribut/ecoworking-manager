<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceLineCalculator;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        // Une facture émise est figée (§3.6) : tout le formulaire passe en lecture
        // seule dès qu'elle n'est plus un brouillon (la Policy bloque déjà l'update).
        $lockedAfterIssue = fn (?Invoice $record): bool => $record !== null && $record->status !== InvoiceStatus::Draft;

        return $schema
            ->components([
                Section::make('Facture')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Numéro')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Attribué à l\'émission')
                            ->visible(fn (?Invoice $record): bool => $record !== null),
                        TextInput::make('status')
                            ->label('Statut')
                            ->formatStateUsing(fn ($state): string => $state instanceof InvoiceStatus ? $state->getLabel() : (string) $state)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Invoice $record): bool => $record !== null),
                        MorphToSelect::make('billable')
                            ->label('Entité facturée')
                            ->types([
                                MorphToSelect\Type::make(Company::class)
                                    ->titleAttribute('legal_name')
                                    ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->name),
                                MorphToSelect\Type::make(User::class)
                                    ->titleAttribute('email')
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})"),
                            ])
                            ->searchable()
                            ->required()
                            ->disabled($lockedAfterIssue),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled($lockedAfterIssue),
                    ]),

                Section::make('Lignes')
                    ->description('Montants calculés côté serveur et figés à l\'émission (§3.6).')
                    ->schema([
                        Repeater::make('lines')
                            ->label('Lignes de facture')
                            ->relationship()
                            ->columns(12)
                            ->disabled($lockedAfterIssue)
                            ->defaultItems(1)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->columnSpan(12),
                                TextInput::make('quantity')
                                    ->label('Qté')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('unit_price_ht')
                                    ->label('PU HT')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('€')
                                    ->columnSpan(3),
                                TextInput::make('discount_rate')
                                    ->label('Remise')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->columnSpan(3),
                                TextInput::make('vat_rate')
                                    ->label('TVA')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(20)
                                    ->required()
                                    ->suffix('%')
                                    ->columnSpan(3),
                            ])
                            // Totaux figés calculés côté serveur — jamais confiance au front (§3.6).
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, ...InvoiceLineCalculator::totals($data)])
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => [...$data, ...InvoiceLineCalculator::totals($data)]),
                    ]),

                Section::make('Totaux figés')
                    ->columns(3)
                    ->visible($lockedAfterIssue)
                    ->schema([
                        TextInput::make('subtotal_ht')
                            ->label('Sous-total HT')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('€'),
                        TextInput::make('total_vat')
                            ->label('TVA')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('€'),
                        TextInput::make('total_ttc')
                            ->label('Total TTC')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('€'),
                    ]),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encaissement')
                    ->columns(2)
                    ->schema([
                        // Seules les factures émises encore encaissables : ni les
                        // brouillons (pas de numéro), ni les annulées, ni les avoirs.
                        Select::make('invoice_id')
                            ->label('Facture')
                            ->relationship(
                                'invoice',
                                'number',
                                fn ($query) => $query
                                    ->whereNotNull('number')
                                    ->where('is_credit_note', false)
                                    ->where('status', '!=', InvoiceStatus::Cancelled->value),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Invoice $record): string => $record->number ?? "#{$record->id}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('method')
                            ->label('Moyen de paiement')
                            ->options(PaymentMethod::class)
                            ->required(),
                        TextInput::make('amount')
                            ->label('Montant')
                            ->numeric()
                            // Pas de montant nul/négatif : un remboursement passe
                            // par le flux avoir, pas par un paiement inversé.
                            ->minValue(0.01)
                            ->required()
                            ->prefix('€'),
                        DatePicker::make('paid_at')
                            ->label('Encaissé le')
                            ->required()
                            ->default(now()),
                        TextInput::make('reference')
                            ->label('Référence')
                            ->maxLength(120),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

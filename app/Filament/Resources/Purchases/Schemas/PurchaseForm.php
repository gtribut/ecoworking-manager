<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\OfferType;
use App\Enums\TicketType;
use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Achat')
                    ->columns(2)
                    ->schema([
                        Select::make('offer_id')
                            ->label('Offre')
                            ->relationship(
                                'offer',
                                'name',
                                fn ($query) => $query->whereIn('type', [OfferType::OneShot->value, OfferType::Pack->value]),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Offer $record): string => "{$record->name} ({$record->code})")
                            ->searchable()
                            ->preload()
                            ->live()
                            // Pré-remplit le snapshot depuis l'offre ; les champs
                            // restent éditables (§3.6 : prix figé à l'achat).
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $offer = $state ? Offer::find($state) : null;
                                if ($offer === null) {
                                    return;
                                }
                                $set('ticket_type', $offer->ticket_type?->value);
                                $set('quantity', $offer->quantity_per_purchase);
                                $set('unit_price_ht', (string) $offer->unit_price_ht);
                                $set('vat_rate', (string) $offer->vat_rate);
                                $set('label', $offer->name);
                            })
                            ->helperText('Pré-remplit le prix ; ajustable (le montant est figé à l\'achat).'),
                        Select::make('user_id')
                            ->label('Bénéficiaire')
                            ->relationship('user', 'email')
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('ticket_type')
                            ->label('Type de ticket')
                            ->options(TicketType::class)
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantité')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
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
                            ->searchable(),
                    ]),

                Section::make('Montant figé & date')
                    ->description('Snapshot conservé même si le catalogue évolue (§3.6).')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit_price_ht')
                            ->label('Prix unitaire HT')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->prefix('€'),
                        TextInput::make('vat_rate')
                            ->label('Taux de TVA')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(20)
                            ->required()
                            ->suffix('%'),
                        DateTimePicker::make('purchased_at')
                            ->label('Acheté le')
                            ->required()
                            ->default(now()),
                        TextInput::make('label')
                            ->label('Libellé')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

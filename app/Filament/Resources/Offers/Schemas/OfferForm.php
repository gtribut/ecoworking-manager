<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Schemas;

use App\Enums\BillingPeriod;
use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use App\Enums\TicketType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Code (SKU)')
                            ->required()
                            ->maxLength(40)
                            ->unique(ignoreRecord: true)
                            ->helperText('Identifiant stable du catalogue (ex. RESIDENT_FULL).'),
                        TextInput::make('name')
                            ->label('Nom commercial')
                            ->required()
                            ->maxLength(150),
                        Select::make('type')
                            ->label('Type')
                            ->options(OfferType::class)
                            ->required()
                            ->live()
                            ->default(OfferType::Subscription->value),
                        Select::make('subscriber_kind')
                            ->label('Souscripteur')
                            ->options(SubscriberKind::class)
                            ->required()
                            ->default(SubscriberKind::Member->value),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Tarification')
                    ->description('Le prix n\'est PAS figé chez l\'abonné : toute modification s\'applique à la prochaine facturation de tous les abonnements (§6.7).')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit_price_ht')
                            ->label('Prix unitaire HT')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        TextInput::make('vat_rate')
                            ->label('Taux de TVA')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(20)
                            ->suffix('%'),
                        Select::make('billing_period')
                            ->label('Périodicité')
                            ->options(BillingPeriod::class)
                            ->visible(fn (Get $get): bool => $get('type') === OfferType::Subscription->value)
                            ->helperText('Abonnements uniquement.'),
                    ]),

                Section::make('Quantité & tickets')
                    ->columns(2)
                    ->schema([
                        TextInput::make('quantity_per_purchase')
                            ->label('Quantité par achat')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Nombre de tickets crédités par achat (packs).'),
                        Select::make('ticket_type')
                            ->label('Type de ticket')
                            ->options(TicketType::class)
                            ->visible(fn (Get $get): bool => in_array($get('type'), [OfferType::OneShot->value, OfferType::Pack->value], true))
                            ->helperText('Offres à l\'unité / packs.'),
                        TextInput::make('max_per_user')
                            ->label('Maximum par utilisateur')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Illimité'),
                        Toggle::make('requires_active_resident')
                            ->label('Réservé aux résidents actifs')
                            ->inline(false),
                    ]),

                Section::make('Visibilité')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active (catalogue courant)')
                            ->default(true),
                        Toggle::make('is_public')
                            ->label('Visible au portail membre')
                            ->default(true),
                        TextInput::make('display_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),
                        TagsInput::make('features')
                            ->label('Caractéristiques')
                            ->placeholder('Ajouter…')
                            ->helperText('Liste affichée sur la fiche offre.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

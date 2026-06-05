<?php

declare(strict_types=1);

namespace App\Filament\Resources\Resources\Schemas;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ResourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(ResourceType::class)
                            ->required()
                            ->live(),
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                        // Affectation : bureaux uniquement (§6.10) — NULL pour les salles.
                        Select::make('assignment')
                            ->label('Affectation')
                            ->options(ResourceAssignment::class)
                            ->visible(fn (Get $get): bool => $get('type') === ResourceType::Desk->value),
                        TextInput::make('floor')
                            ->label('Étage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10),
                    ]),

                Section::make('Capacité & caractéristiques')
                    ->columns(2)
                    ->schema([
                        // Capacité pertinente pour les salles ; un bureau accueille 1 personne.
                        TextInput::make('capacity')
                            ->label('Capacité')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->visible(fn (Get $get): bool => $get('type') !== ResourceType::Desk->value),
                        TextInput::make('external_half_day_price_ht')
                            ->label('Tarif externe demi-journée HT')
                            ->helperText('Référence ; le prix réel reste l\'offre ticket (§4.3).')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->visible(fn (Get $get): bool => $get('type') === ResourceType::MeetingRoom->value),
                        TagsInput::make('features')
                            ->label('Caractéristiques')
                            ->placeholder('TV, tableau blanc, écran…')
                            ->columnSpanFull(),
                        Toggle::make('requires_admin')
                            ->label('Réservation soumise à validation admin')
                            ->default(false),
                    ]),

                Section::make('Plan & synchronisation')
                    ->columns(2)
                    ->schema([
                        // Mapping data-desk-id du plan SVG — bureaux uniquement.
                        TextInput::make('svg_desk_id')
                            ->label('Identifiant SVG (plan)')
                            ->maxLength(40)
                            ->unique(ignoreRecord: true)
                            ->visible(fn (Get $get): bool => $get('type') === ResourceType::Desk->value),
                        TextInput::make('google_calendar_color')
                            ->label('Couleur Google Calendar')
                            ->maxLength(20),
                        KeyValue::make('opening_hours')
                            ->label('Plages d\'ouverture')
                            ->keyLabel('Jour')
                            ->valueLabel('Horaires')
                            ->helperText('Défaut applicatif : 24/24 résident, 9h-18h ouvré externe.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Visibilité')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                        Toggle::make('is_out_of_service')
                            ->label('Hors service')
                            ->default(false),
                        TextInput::make('display_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}

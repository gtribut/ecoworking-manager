<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\PaymentMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Type & statut')
                    ->columns(2)
                    ->schema([
                        Select::make('entity_type')
                            ->label('Nature')
                            ->options(CompanyType::class)
                            ->required()
                            ->live()
                            ->default(CompanyType::Company->value),
                        Select::make('status')
                            ->label('Statut')
                            ->options(CompanyStatus::class)
                            ->required()
                            ->default(CompanyStatus::Active->value),
                    ]),

                Section::make('Identité — entreprise')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('entity_type') === CompanyType::Company->value)
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Raison sociale')
                            ->required(fn (Get $get): bool => $get('entity_type') === CompanyType::Company->value)
                            ->maxLength(255),
                        TextInput::make('legal_form')
                            ->label('Forme juridique')
                            ->placeholder('SAS, SARL, SCOP…')
                            ->maxLength(50),
                        TextInput::make('siret')
                            ->label('SIRET')
                            // Champ texte (PAS ->numeric()) : un SIRET peut commencer
                            // par 0 — un cast numérique perdrait les zéros de tête.
                            ->regex('/^\d{14}$/')
                            ->length(14)
                            ->helperText('14 chiffres.'),
                        TextInput::make('vat_number')
                            ->label('N° TVA intracom.')
                            ->maxLength(20),
                        TextInput::make('ape_code')
                            ->label('Code APE/NAF')
                            ->maxLength(10),
                    ]),

                Section::make('Identité — particulier')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('entity_type') === CompanyType::Individual->value)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->required(fn (Get $get): bool => $get('entity_type') === CompanyType::Individual->value)
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required(fn (Get $get): bool => $get('entity_type') === CompanyType::Individual->value)
                            ->maxLength(100),
                        DatePicker::make('birth_date')
                            ->label('Date de naissance')
                            ->maxDate('today'),
                    ]),

                Section::make('Coordonnées de facturation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('billing_email')
                            ->label('Email de facturation')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('address_line1')
                            ->label('Adresse')
                            ->maxLength(255),
                        TextInput::make('address_line2')
                            ->label('Complément d\'adresse')
                            ->maxLength(255),
                        TextInput::make('postal_code')
                            ->label('Code postal')
                            ->maxLength(16),
                        TextInput::make('city')
                            ->label('Ville')
                            ->maxLength(120),
                        TextInput::make('country')
                            ->label('Pays')
                            ->default('FR')
                            ->length(2)
                            ->helperText('Code ISO 2 lettres.'),
                    ]),

                Section::make('Paiement & mandat SEPA')
                    ->columns(2)
                    ->schema([
                        Select::make('preferred_payment_method')
                            ->label('Moyen de paiement préféré')
                            ->options(PaymentMethod::class),
                        TextInput::make('sepa_iban_last4')
                            ->label('IBAN — 4 derniers chiffres')
                            // Champ texte (PAS ->numeric()) : « 0123 » doit garder son zéro.
                            ->regex('/^\d{4}$/')
                            ->length(4)
                            // RGPD §3.4 : jamais l'IBAN complet en clair.
                            ->helperText('4 derniers chiffres uniquement — jamais l\'IBAN complet.'),
                        TextInput::make('sepa_mandate_reference')
                            ->label('Référence du mandat')
                            ->maxLength(64),
                        DatePicker::make('sepa_mandate_signed_at')
                            ->label('Date de signature du mandat')
                            ->maxDate('today'),
                    ]),

                Section::make('Remise négociée')
                    ->description('Unique mécanisme de remise (PRD §6.4) — appliqué à la facturation des abonnements.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('discount_rate')
                            ->label('Taux de remise')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        TextInput::make('discount_scope')
                            ->label('Portée de la remise')
                            ->maxLength(40),
                        TextInput::make('discount_note')
                            ->label('Note sur la remise')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes internes')
                    ->collapsed()
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes admin')
                            ->rows(3),
                    ]),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\OfferType;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Offre & parties')
                    ->description('Aucun montant n\'est figé ici : il est recalculé à chaque facturation depuis le catalogue courant, modulé par la remise de l\'entité (§3.6 / §6.4).')
                    ->columns(2)
                    ->schema([
                        Select::make('offer_id')
                            ->label('Offre')
                            ->relationship(
                                'offer',
                                'name',
                                fn ($query) => $query->where('type', OfferType::Subscription->value),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Offer $record): string => "{$record->name} ({$record->code})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->label('Statut')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->default(SubscriptionStatus::Active->value),
                        MorphToSelect::make('subscriber')
                            ->label('Souscripteur')
                            ->types([
                                MorphToSelect\Type::make(User::class)
                                    ->titleAttribute('email')
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})"),
                                MorphToSelect\Type::make(Company::class)
                                    ->titleAttribute('legal_name')
                                    ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->name),
                            ])
                            ->searchable()
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
                            ->searchable()
                            ->required(),
                    ]),

                Section::make('Période & facturation')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('starts_at')
                            ->label('Début')
                            ->required()
                            ->default(now()),
                        DatePicker::make('ends_at')
                            ->label('Fin')
                            ->after('starts_at')
                            ->helperText('Vide = sans terme.'),
                        TextInput::make('billing_day')
                            ->label('Jour de facturation')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->default(1)
                            ->required()
                            ->helperText('1 à 28.'),
                    ]),

                Section::make('Suivi')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('cancel_reason')
                            ->label('Motif d\'annulation')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

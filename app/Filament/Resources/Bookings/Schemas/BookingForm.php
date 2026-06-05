<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\ResourceType;
use App\Models\Company;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Réservation')
                    ->columns(2)
                    ->schema([
                        // Les bookings concernent les SALLES uniquement ; les bureaux
                        // passent par desk_occupations (cf. Booking model §6.8).
                        Select::make('resource_id')
                            ->label('Salle')
                            ->relationship(
                                'resource',
                                'name',
                                fn ($query) => $query->whereIn('type', [
                                    ResourceType::MeetingRoom->value,
                                    ResourceType::EventRoom->value,
                                ]),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('user_id')
                            ->label('Membre')
                            ->relationship('user', 'email')
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                            ->searchable()
                            ->preload(),
                        TextInput::make('title')
                            ->label('Objet')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Statut')
                            ->options(BookingStatus::class)
                            ->default(BookingStatus::Confirmed->value)
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label('Début')
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Fin')
                            ->seconds(false)
                            ->after('starts_at')
                            ->required(),
                    ]),

                Section::make('Facturation')
                    ->description('Snapshot du prix figé sur la réservation si payante (§3.6).')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_internal')
                            ->label('Interne / gratuite')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
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
                            ->visible(fn (Get $get): bool => $get('is_internal') !== true),
                        TextInput::make('price_ht')
                            ->label('Prix HT')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->visible(fn (Get $get): bool => $get('is_internal') !== true),
                        TextInput::make('vat_rate')
                            ->label('Taux de TVA')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => $get('is_internal') !== true),
                    ]),

                Section::make('Annulation & synchronisation')
                    ->columns(2)
                    ->schema([
                        Textarea::make('cancel_reason')
                            ->label('Motif d\'annulation')
                            ->rows(2)
                            ->visible(fn (Get $get): bool => $get('status') === BookingStatus::Cancelled->value),
                        DateTimePicker::make('cancelled_at')
                            ->label('Annulée le')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $get('status') === BookingStatus::Cancelled->value),
                        TextInput::make('google_calendar_event_id')
                            ->label('ID événement Google Calendar')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

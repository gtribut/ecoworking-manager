<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences\Schemas;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Saisie d'une absence par l'admin (PRD §4.8.2 : « la manageuse pose les
 * vacances de quelqu'un à sa demande »). Le bureau n'est pas saisissable : il
 * est déduit du bureau attitré du membre par `PresenceService`.
 */
class DeskAbsenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Absence')
                    ->columns(2)
                    ->schema([
                        // Seuls les membres dotés d'un bureau attitré peuvent
                        // être absents « de leur bureau » (PRD §3.4.6).
                        Select::make('user_id')
                            ->label('Membre')
                            ->relationship(
                                'user',
                                'email',
                                fn (Builder $query) => $query->whereHas(
                                    'memberProfile',
                                    fn (Builder $profile) => $profile->whereNotNull('desk_id'),
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText('Le bureau libéré est celui attitré au membre.'),
                        Select::make('period')
                            ->label('Période')
                            ->options(Period::class)
                            ->default(Period::FullDay->value)
                            ->required(),
                        DatePicker::make('date_start')
                            ->label('Date de début')
                            ->required(),
                        DatePicker::make('date_end')
                            ->label('Date de fin')
                            ->afterOrEqual('date_start')
                            ->helperText('Vide = jour unique, ou récurrence sans fin.'),
                        Select::make('recurrence_type')
                            ->label('Récurrence')
                            ->options(DeskAbsenceRecurrence::class)
                            ->default(DeskAbsenceRecurrence::None->value)
                            ->live()
                            ->required(),
                        Select::make('recurrence_day_of_week')
                            ->label('Jour de récurrence')
                            ->options([
                                1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                                5 => 'Vendredi', 6 => 'Samedi', 0 => 'Dimanche',
                            ])
                            ->visible(fn (Get $get): bool => $get('recurrence_type') === DeskAbsenceRecurrence::Weekly->value)
                            ->required(fn (Get $get): bool => $get('recurrence_type') === DeskAbsenceRecurrence::Weekly->value),
                        Textarea::make('notes')
                            ->label('Note interne')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Visible du back-office et du membre concerné uniquement.'),
                    ]),
            ]);
    }
}

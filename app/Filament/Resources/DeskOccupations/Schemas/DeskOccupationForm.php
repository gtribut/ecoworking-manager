<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Schemas;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\ResourceType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeskOccupationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Occupation')
                    ->columns(2)
                    ->schema([
                        // Bureaux uniquement (desk_id → resources de type desk).
                        Select::make('desk_id')
                            ->label('Bureau')
                            ->relationship(
                                'desk',
                                'name',
                                fn ($query) => $query->where('type', ResourceType::Desk->value),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('user_id')
                            ->label('Occupant')
                            ->relationship('user', 'email')
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('date')
                            ->label('Date')
                            ->required(),
                        Select::make('period')
                            ->label('Créneau')
                            ->options(Period::class)
                            ->required(),
                        Select::make('source')
                            ->label('Origine')
                            ->options(DeskOccupationSource::class)
                            ->required(),
                        Select::make('status')
                            ->label('Statut')
                            ->options(DeskOccupationStatus::class)
                            ->default(DeskOccupationStatus::Present->value)
                            ->required(),
                    ]),
            ]);
    }
}

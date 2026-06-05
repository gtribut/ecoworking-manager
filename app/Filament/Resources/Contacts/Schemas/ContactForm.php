<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactRole;
use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Entité')
                            ->relationship('company')
                            ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('role')
                            ->label('Rôle')
                            ->options(ContactRole::class)
                            ->required(),
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(30),
                        Toggle::make('is_primary')
                            ->label('Contact principal')
                            ->helperText('Interlocuteur de référence pour ce rôle.'),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

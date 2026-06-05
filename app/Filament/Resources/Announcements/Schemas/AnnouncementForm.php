<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenu')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(AnnouncementType::class)
                            ->required()
                            ->live(),
                        Select::make('visibility')
                            ->label('Visibilité')
                            ->options(Audience::class)
                            ->default(Audience::All->value)
                            ->required(),
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Corps')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),
                        FileUpload::make('cover_image_path')
                            ->label('Image de couverture')
                            ->image()
                            ->directory('announcements/covers')
                            ->columnSpanFull(),
                    ]),

                // Bloc événement : pertinent uniquement pour le type "event".
                Section::make('Événement')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('type') === AnnouncementType::Event->value)
                    ->schema([
                        DateTimePicker::make('event_starts_at')
                            ->label('Début')
                            ->seconds(false),
                        DateTimePicker::make('event_ends_at')
                            ->label('Fin')
                            ->seconds(false)
                            ->after('event_starts_at'),
                        TextInput::make('location')
                            ->label('Lieu')
                            ->maxLength(255),
                        Toggle::make('requires_registration')
                            ->label('Inscription requise')
                            ->default(false)
                            ->live(),
                        TextInput::make('max_participants')
                            ->label('Participants max')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('requires_registration') === true),
                    ]),

                Section::make('Publication')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(AnnouncementStatus::class)
                            ->default(AnnouncementStatus::Draft->value)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publiée le')
                            ->seconds(false),
                    ]),
            ]);
    }
}

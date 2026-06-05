<?php

declare(strict_types=1);

namespace App\Filament\Resources\InternalDocuments\Schemas;

use App\Enums\Audience;
use App\Enums\InternalDocumentType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InternalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(InternalDocumentType::class)
                            ->required(),
                        // Changement de version → re-validation requise côté membre (§4.5).
                        TextInput::make('version')
                            ->label('Version')
                            ->required()
                            ->maxLength(20)
                            ->helperText('Modifier la version impose une re-validation par les membres.'),
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Corps')
                            ->rows(6)
                            ->columnSpanFull(),
                        FileUpload::make('pdf_path')
                            ->label('PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('internal-documents')
                            ->columnSpanFull(),
                    ]),

                Section::make('Diffusion')
                    ->columns(3)
                    ->schema([
                        Select::make('audience')
                            ->label('Audience')
                            ->options(Audience::class)
                            ->default(Audience::All->value)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publié le')
                            ->seconds(false),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
            ]);
    }
}

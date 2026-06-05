<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdministrativeDocuments\Schemas;

use App\Enums\AdministrativeDocumentType;
use App\Models\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdministrativeDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document administratif')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Entité')
                            ->relationship('company', 'legal_name')
                            ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->label('Type')
                            ->options(AdministrativeDocumentType::class)
                            ->required(),
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('document_date')
                            ->label('Date du document'),
                        FileUpload::make('pdf_path')
                            ->label('PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('administrative-documents')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

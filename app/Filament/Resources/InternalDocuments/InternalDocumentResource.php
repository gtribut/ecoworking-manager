<?php

declare(strict_types=1);

namespace App\Filament\Resources\InternalDocuments;

use App\Filament\Resources\InternalDocuments\Pages\CreateInternalDocument;
use App\Filament\Resources\InternalDocuments\Pages\EditInternalDocument;
use App\Filament\Resources\InternalDocuments\Pages\ListInternalDocuments;
use App\Filament\Resources\InternalDocuments\Schemas\InternalDocumentForm;
use App\Filament\Resources\InternalDocuments\Tables\InternalDocumentsTable;
use App\Models\InternalDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InternalDocumentResource extends Resource
{
    protected static ?string $model = InternalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Communication & documents';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'document commun';

    protected static ?string $pluralModelLabel = 'documents communs';

    protected static ?string $navigationLabel = 'Documents communs';

    public static function form(Schema $schema): Schema
    {
        return InternalDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InternalDocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInternalDocuments::route('/'),
            'create' => CreateInternalDocument::route('/create'),
            'edit' => EditInternalDocument::route('/{record}/edit'),
        ];
    }
}

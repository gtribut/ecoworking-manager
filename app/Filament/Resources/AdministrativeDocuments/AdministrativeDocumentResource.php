<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdministrativeDocuments;

use App\Filament\Resources\AdministrativeDocuments\Pages\CreateAdministrativeDocument;
use App\Filament\Resources\AdministrativeDocuments\Pages\EditAdministrativeDocument;
use App\Filament\Resources\AdministrativeDocuments\Pages\ListAdministrativeDocuments;
use App\Filament\Resources\AdministrativeDocuments\Schemas\AdministrativeDocumentForm;
use App\Filament\Resources\AdministrativeDocuments\Tables\AdministrativeDocumentsTable;
use App\Models\AdministrativeDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdministrativeDocumentResource extends Resource
{
    protected static ?string $model = AdministrativeDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Communication & documents';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'document administratif';

    protected static ?string $pluralModelLabel = 'documents administratifs';

    protected static ?string $navigationLabel = 'Documents entités';

    public static function form(Schema $schema): Schema
    {
        return AdministrativeDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdministrativeDocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdministrativeDocuments::route('/'),
            'create' => CreateAdministrativeDocument::route('/create'),
            'edit' => EditAdministrativeDocument::route('/{record}/edit'),
        ];
    }
}

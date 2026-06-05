<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations;

use App\Filament\Resources\DeskOccupations\Pages\CreateDeskOccupation;
use App\Filament\Resources\DeskOccupations\Pages\EditDeskOccupation;
use App\Filament\Resources\DeskOccupations\Pages\ListDeskOccupations;
use App\Filament\Resources\DeskOccupations\Schemas\DeskOccupationForm;
use App\Filament\Resources\DeskOccupations\Tables\DeskOccupationsTable;
use App\Models\DeskOccupation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeskOccupationResource extends Resource
{
    protected static ?string $model = DeskOccupation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|UnitEnum|null $navigationGroup = 'Espaces & réservations';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'occupation de bureau';

    protected static ?string $pluralModelLabel = 'occupations de bureaux';

    protected static ?string $navigationLabel = 'Occupations bureaux';

    public static function form(Schema $schema): Schema
    {
        return DeskOccupationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeskOccupationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeskOccupations::route('/'),
            'create' => CreateDeskOccupation::route('/create'),
            'edit' => EditDeskOccupation::route('/{record}/edit'),
        ];
    }
}

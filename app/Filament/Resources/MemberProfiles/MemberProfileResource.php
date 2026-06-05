<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberProfiles;

use App\Filament\Resources\MemberProfiles\Pages\CreateMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\MemberProfiles\Tables\MemberProfilesTable;
use App\Models\MemberProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MemberProfileResource extends Resource
{
    protected static ?string $model = MemberProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Membres & entités';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'profil membre';

    protected static ?string $pluralModelLabel = 'profils membres';

    protected static ?string $navigationLabel = 'Profils membres';

    public static function form(Schema $schema): Schema
    {
        return MemberProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberProfiles::route('/'),
            'create' => CreateMemberProfile::route('/create'),
            'edit' => EditMemberProfile::route('/{record}/edit'),
        ];
    }
}

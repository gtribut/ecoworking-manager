<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences;

use App\Enums\Permission;
use App\Filament\Resources\DeskAbsences\Pages\CreateDeskAbsence;
use App\Filament\Resources\DeskAbsences\Pages\EditDeskAbsence;
use App\Filament\Resources\DeskAbsences\Pages\ListDeskAbsences;
use App\Filament\Resources\DeskAbsences\Schemas\DeskAbsenceForm;
use App\Filament\Resources\DeskAbsences\Tables\DeskAbsencesTable;
use App\Models\DeskAbsence;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Absences déclarées sur les bureaux attitrés (PRD §4.8.2, onglet « Listing
 * absences déclarées »). L'admin consulte, saisit pour un membre et corrige —
 * la logique métier vit dans `PresenceService`, l'autorisation dans
 * `DeskAbsencePolicy` (permission `declare-presence-for-others`).
 */
class DeskAbsenceResource extends Resource
{
    protected static ?string $model = DeskAbsence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Espaces & réservations';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'absence déclarée';

    protected static ?string $pluralModelLabel = 'absences déclarées';

    protected static ?string $navigationLabel = 'Absences bureaux';

    /**
     * Back-office uniquement (défense en profondeur, en plus de
     * `canAccessPanel`) : la Policy autorise aussi le membre à gérer SES
     * absences depuis le portail, elle ne peut donc pas garder ce panneau.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->can(Permission::DeclarePresenceForOthers->value));
    }

    public static function form(Schema $schema): Schema
    {
        return DeskAbsenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeskAbsencesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeskAbsences::route('/'),
            'create' => CreateDeskAbsence::route('/create'),
            'edit' => EditDeskAbsence::route('/{record}/edit'),
        ];
    }
}

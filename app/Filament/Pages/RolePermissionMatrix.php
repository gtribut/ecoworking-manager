<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role as SpatieRole;
use UnitEnum;

/**
 * Matrice rôles → permissions en LECTURE SEULE (C12.8b, PRD §4.13).
 *
 * La composition est figée dans le code (enums Role/Permission + composition
 * seedée par PermissionSeeder, matrices PRD §2.5/§2.6) : pas de création ni
 * d'édition de rôles/permissions dans l'app — les Policies et la contrainte
 * XOR (ExclusiveUsageRole) reposent sur ces 6 rôles précis. L'AFFECTATION des
 * rôles aux membres, elle, se fait dans la fiche membre (UserResource).
 *
 * La page lit la composition réelle en DB (pas les enums) : elle reflète donc
 * fidèlement ce que spatie/laravel-permission applique en production.
 */
class RolePermissionMatrix extends Page
{
    protected string $view = 'filament.pages.role-permission-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Système';

    protected static ?string $navigationLabel = 'Rôles & permissions';

    protected static ?string $title = 'Rôles & permissions';

    protected static ?int $navigationSort = 2;

    /** Back-office admin uniquement (défense en profondeur, en plus de canAccessPanel). */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        // Composition réelle en DB, ordonnée selon les enums (1 requête + pivot).
        $dbRoles = SpatieRole::query()->with('permissions')->get()->keyBy('name');

        $roles = collect(RoleEnum::cases())
            ->filter(fn (RoleEnum $role): bool => $dbRoles->has($role->value))
            ->values();

        /** @var array<string, array<string, bool>> $matrix permission → (rôle → accordée) */
        $matrix = [];
        foreach (PermissionEnum::cases() as $permission) {
            foreach ($roles as $role) {
                $matrix[$permission->value][$role->value] = $dbRoles[$role->value]
                    ->permissions
                    ->contains('name', $permission->value);
            }
        }

        return [
            'roles' => $roles,
            'matrix' => $matrix,
            'adminPermissions' => array_map(
                static fn (PermissionEnum $permission): string => $permission->value,
                PermissionEnum::adminPermissions(),
            ),
        ];
    }
}

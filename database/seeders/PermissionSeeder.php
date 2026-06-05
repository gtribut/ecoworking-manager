<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée les permissions granulaires (PRD §2.8) et les compose en rôles
 * (matrices PRD §2.5 portail / §2.6 admin). Idempotent (findOrCreate +
 * syncPermissions). Suppose les rôles déjà créés ({@see RoleSeeder}).
 *
 * Note : `admin` détient toutes les permissions back-office, mais l'autorisation
 * réelle passe d'abord par les Policies (`User::isAdmin()`), qui court-circuitent
 * l'isolation. Les permissions servent au gating fin (Filament, SPA).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // Rafraîchir le cache spatie : syncPermissions résout les permissions
        // depuis le cache en mémoire, qui ne connaît pas encore celles créées
        // ci-dessus (sinon « There is no permission named … »).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->rolePermissionMap() as $roleValue => $permissions) {
            $role = Role::findOrCreate($roleValue, 'web');
            $role->syncPermissions(array_map(
                static fn (PermissionEnum $p): string => $p->value,
                $permissions,
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Composition rôle → permissions (PRD §2.5/§2.6).
     *
     * @return array<string, list<PermissionEnum>>
     */
    private function rolePermissionMap(): array
    {
        // Tronc commun aux rôles d'usage avec accès portail « membre ».
        $memberBase = [
            PermissionEnum::ViewOwnBookings,
            PermissionEnum::ViewBookingsCalendar,
            PermissionEnum::CreateOwnBooking,
            PermissionEnum::ManageOwnBooking,
            PermissionEnum::RegisterEvent,
            PermissionEnum::ValidateInternalDocument,
        ];

        return [
            // resident / staff : tronc membre + annuaire (PRD §2.5).
            RoleEnum::Resident->value => [...$memberBase, PermissionEnum::ViewAnnuaire],
            RoleEnum::Staff->value => [...$memberBase, PermissionEnum::ViewAnnuaire],

            // additional : tronc membre + annuaire, sans occupation bureau attitré.
            RoleEnum::Additional->value => [...$memberBase, PermissionEnum::ViewAnnuaire],

            // external : pas d'annuaire ; réserve une salle via ticket (payant).
            RoleEnum::External->value => [
                PermissionEnum::ViewOwnBookings,
                PermissionEnum::ViewBookingsCalendar,
                PermissionEnum::CreatePaidBooking,
                PermissionEnum::ManageOwnBooking,
                PermissionEnum::RegisterEvent,
                PermissionEnum::ValidateInternalDocument,
                // PurchaseNomadTickets : V2 (PRD §2.5), non attribuée en MVP.
            ],

            // billing_contact : rôle additionnel, n'ajoute que la visibilité factu.
            RoleEnum::BillingContact->value => [
                PermissionEnum::ViewBillingSection,
                PermissionEnum::ViewEntityInvoices,
                PermissionEnum::ViewEntityAdminDocuments,
                PermissionEnum::RequestEntityModification,
            ],

            // admin : toutes les permissions back-office (PRD §2.6).
            RoleEnum::Admin->value => PermissionEnum::adminPermissions(),
        ];
    }
}

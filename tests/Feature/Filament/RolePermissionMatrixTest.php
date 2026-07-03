<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\RolePermissionMatrix;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.8b — Matrice rôles → permissions en lecture seule (PRD §4.13). La
 * composition vit dans PermissionSeeder (testée dans RolePermissionTest) —
 * ici on vérifie que la page la restitue fidèlement depuis la DB, et qu'elle
 * est réservée aux admins.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

it('rend la matrice avec les rôles et permissions seedés', function () {
    Livewire::test(RolePermissionMatrix::class)
        ->assertOk()
        ->assertSee(Role::Resident->getLabel())
        ->assertSee(Role::Admin->getLabel())
        ->assertSee(Role::BillingContact->getLabel())
        ->assertSee(Permission::ViewAnnuaire->value)
        ->assertSee(Permission::ManageInvoices->value);
});

it('reflète fidèlement la composition seedée (PRD §2.5 / §2.6)', function () {
    Livewire::test(RolePermissionMatrix::class)
        ->assertViewHas('matrix', function (array $matrix): bool {
            return $matrix[Permission::ViewAnnuaire->value][Role::Resident->value] === true
                // external n'a pas l'annuaire (PRD §2.5).
                && $matrix[Permission::ViewAnnuaire->value][Role::External->value] === false
                // billing_contact ne voit que la facturation.
                && $matrix[Permission::ViewEntityInvoices->value][Role::BillingContact->value] === true
                && $matrix[Permission::ManageInvoices->value][Role::BillingContact->value] === false
                // admin détient tout le back-office.
                && $matrix[Permission::ManageInvoices->value][Role::Admin->value] === true;
        });
});

it('refuse l\'accès à un membre non admin', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(RolePermissionMatrix::class)->assertForbidden();
});

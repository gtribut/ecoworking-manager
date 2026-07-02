<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

/**
 * M7 — Contrainte XOR des rôles d'usage (PRD §2.4) appliquée au Select `roles`
 * du formulaire admin : au plus UN rôle parmi resident/additional/external/staff.
 * Les rôles additionnels (admin, billing_contact) se cumulent librement.
 * L'état du Select relationnel = IDs spatie (résolus en noms par la règle).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

/** ID spatie d'un rôle par son enum. */
function roleId(Role $role): int
{
    return SpatieRole::findByName($role->value, 'web')->id;
}

it('refuse d\'attribuer deux rôles d\'usage simultanément (resident + external)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password' => 'secret-password',
            'roles' => [roleId(Role::Resident), roleId(Role::External)],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect(User::where('email', 'jean.dupont@example.com')->exists())->toBeFalse();
});

it('accepte un rôle d\'usage cumulé avec un rôle additionnel (resident + billing_contact)', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie.martin@example.com',
            'password' => 'secret-password',
            'roles' => [roleId(Role::Resident), roleId(Role::BillingContact)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'marie.martin@example.com')->firstOrFail();

    expect($user->roles->pluck('name')->sort()->values()->all())
        ->toBe([Role::BillingContact->value, Role::Resident->value]);
});

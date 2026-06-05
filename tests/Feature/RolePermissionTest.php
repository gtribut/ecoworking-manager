<?php

declare(strict_types=1);

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Rules\ExclusiveUsageRole;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** C2.5 — composition rôles→permissions (PRD §2.5/§2.6) et contrainte XOR (§2.4). */
it('compose les rôles selon les matrices de permissions du PRD', function () {
    $this->seed(PermissionSeeder::class);

    $resident = Role::findByName(RoleEnum::Resident->value, 'web');
    expect($resident->hasPermissionTo(PermissionEnum::ViewAnnuaire->value))->toBeTrue()
        ->and($resident->hasPermissionTo(PermissionEnum::CreateOwnBooking->value))->toBeTrue()
        ->and($resident->hasPermissionTo(PermissionEnum::ViewEntityInvoices->value))->toBeFalse();

    // external : réserve via ticket (payant), pas d'annuaire (PRD §2.5).
    $external = Role::findByName(RoleEnum::External->value, 'web');
    expect($external->hasPermissionTo(PermissionEnum::CreatePaidBooking->value))->toBeTrue()
        ->and($external->hasPermissionTo(PermissionEnum::CreateOwnBooking->value))->toBeFalse()
        ->and($external->hasPermissionTo(PermissionEnum::ViewAnnuaire->value))->toBeFalse();

    // billing_contact : seulement la visibilité facturation.
    $billing = Role::findByName(RoleEnum::BillingContact->value, 'web');
    expect($billing->hasPermissionTo(PermissionEnum::ViewEntityInvoices->value))->toBeTrue()
        ->and($billing->hasPermissionTo(PermissionEnum::CreateOwnBooking->value))->toBeFalse();

    $admin = Role::findByName(RoleEnum::Admin->value, 'web');
    expect($admin->hasPermissionTo(PermissionEnum::ManageInvoices->value))->toBeTrue();
});

it('est idempotent (rejouable sans doublon de permissions)', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe(count(PermissionEnum::cases()));
});

it('rejette le cumul de deux rôles d\'usage (XOR resident/additional/external/staff)', function () {
    $validator = Validator::make(
        ['roles' => ['resident', 'external']],
        ['roles' => [new ExclusiveUsageRole]],
    );

    expect($validator->fails())->toBeTrue();
});

it('accepte un seul rôle d\'usage cumulé avec des rôles additionnels', function () {
    $validator = Validator::make(
        ['roles' => ['resident', 'billing_contact', 'admin']],
        ['roles' => [new ExclusiveUsageRole]],
    );

    expect($validator->passes())->toBeTrue();
});

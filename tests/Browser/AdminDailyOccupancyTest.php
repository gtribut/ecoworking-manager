<?php

declare(strict_types=1);

use App\Enums\ResourceAssignment;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;

it("affiche l'occupation du jour avec les bureaux du seed (C12.6)", function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, ResourceSeeder::class]);
    $admin = createAdminWithTotp();

    // Un résident attitré au bureau 1 pour peupler la section « Bureaux attitrés »
    // (le bureau doit être marqué attitré ET rattaché au profil du résident).
    $desk = Resource::query()->where('svg_desk_id', 'desk-1')->firstOrFail();
    $desk->update(['assignment' => ResourceAssignment::AssignedResident]);
    $resident = User::factory()->resident()->create([
        'first_name' => 'Rési',
        'last_name' => 'Dente',
    ]);
    MemberProfile::factory()->withDesk($desk->id)->create(['user_id' => $resident->id]);

    loginToAdminPanel($admin)
        ->navigate('/admin/daily-occupancy')
        ->waitForText('Occupation du jour')
        ->assertSee('Capacité bureaux libres restante')
        ->assertSee('Étage 1')
        ->assertSee('Étage 2')
        ->assertSee('Bureaux attitrés')
        ->assertSee('Bureau 1')
        ->assertSee('Rési Dente')
        ->assertSee('Réservations salles du jour');
});

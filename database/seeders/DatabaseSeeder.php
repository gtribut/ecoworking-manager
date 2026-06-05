<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Données de référence (rôles, catalogue, inventaire) — idempotentes.
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            OfferSeeder::class,
            ResourceSeeder::class,
        ]);

        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Ecoworking',
            'email' => 'admin@ecoworking.fr',
        ])->assignRole(RoleEnum::Admin->value);
    }
}

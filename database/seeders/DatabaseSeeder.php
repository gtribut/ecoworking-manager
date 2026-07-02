<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Idempotent (review 06 M6) : rejouable sans violer l'unique sur l'email.
     * Mot de passe admin : jamais de valeur par défaut connue (`password`) sur
     * une adresse réelle — soit `SEED_ADMIN_PASSWORD` (env), soit un mot de
     * passe aléatoire affiché UNE seule fois à la création (à changer ensuite).
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

        $admin = User::query()->firstWhere('email', 'admin@ecoworking.fr');

        if ($admin === null) {
            $password = env('SEED_ADMIN_PASSWORD');
            $generated = $password === null || $password === '';

            if ($generated) {
                $password = Str::password(24);
            }

            $admin = User::factory()->create([
                'first_name' => 'Admin',
                'last_name' => 'Ecoworking',
                'email' => 'admin@ecoworking.fr',
                'password' => Hash::make($password),
            ]);

            if ($generated) {
                $this->command?->warn(sprintf(
                    'Mot de passe admin généré (affiché une seule fois) : %s',
                    $password,
                ));
            }
        }

        $admin->assignRole(RoleEnum::Admin->value);
    }
}

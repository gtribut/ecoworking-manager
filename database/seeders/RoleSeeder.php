<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Crée les 6 rôles applicatifs (data_model §3) via spatie/laravel-permission.
 * Idempotent (firstOrCreate) — rejouable sans doublon.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}

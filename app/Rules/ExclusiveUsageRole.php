<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Contrainte XOR sur les rôles d'usage (PRD §2.4) : un compte peut avoir
 * **au plus un** rôle parmi resident/additional/external/staff. Les rôles
 * additionnels (`admin`, `billing_contact`) sont libres de se cumuler.
 *
 * À utiliser dans le Form Request d'attribution des rôles (back-office).
 * spatie/laravel-permission n'applique pas nativement cette exclusivité.
 *
 * La valeur attendue est un tableau de valeurs de rôles (strings).
 */
final class ExclusiveUsageRole implements ValidationRule
{
    /**
     * @param  mixed  $value  tableau de valeurs de rôles (ex. ['resident', 'billing_contact'])
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $roles = array_map(strval(...), (array) $value);

        $usageRoleValues = array_map(
            static fn (Role $role): string => $role->value,
            Role::usageRoles(),
        );

        $selectedUsageRoles = array_intersect($roles, $usageRoleValues);

        if (count($selectedUsageRoles) > 1) {
            $fail('Un compte ne peut cumuler qu\'un seul rôle d\'usage (résident, personne supplémentaire, externe ou staff).');
        }
    }
}

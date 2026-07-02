<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Contrainte XOR sur les rôles d'usage (PRD §2.4) : un compte peut avoir
 * **au plus un** rôle parmi resident/additional/external/staff. Les rôles
 * additionnels (`admin`, `billing_contact`) sont libres de se cumuler.
 *
 * Appliquée sur le Select `roles` du back-office (UserForm) et utilisable
 * dans un Form Request. spatie/laravel-permission n'applique pas nativement
 * cette exclusivité.
 *
 * La valeur attendue est un tableau de rôles : noms spatie (strings) OU IDs
 * (état du Select relationnel Filament) — les IDs sont résolus en noms.
 */
final class ExclusiveUsageRole implements ValidationRule
{
    /**
     * @param  mixed  $value  tableau de rôles (ex. ['resident', 'billing_contact'] ou [2, 6])
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $roles = $this->resolveRoleNames((array) $value);

        $usageRoleValues = array_map(
            static fn (Role $role): string => $role->value,
            Role::usageRoles(),
        );

        $selectedUsageRoles = array_intersect($roles, $usageRoleValues);

        if (count($selectedUsageRoles) > 1) {
            $fail('Un compte ne peut cumuler qu\'un seul rôle d\'usage (résident, personne supplémentaire, externe ou staff).');
        }
    }

    /**
     * Normalise le tableau soumis en noms de rôles : les entrées numériques
     * (IDs spatie, état du Select Filament) sont résolues en une requête.
     *
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function resolveRoleNames(array $values): array
    {
        $values = array_map(strval(...), array_values($values));

        $ids = array_filter($values, is_numeric(...));
        $names = array_values(array_diff($values, $ids));

        if ($ids !== []) {
            $names = [
                ...$names,
                ...SpatieRole::query()->whereIn('id', $ids)->pluck('name')->all(),
            ];
        }

        return $names;
    }
}

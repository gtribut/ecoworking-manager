<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Helper de contraintes CHECK pour les migrations.
 *
 * Convention data_model §1 : énumérations stockées en `varchar` + CHECK
 * (jamais de type ENUM natif Postgres). La contrainte est nommée de façon
 * déterministe ; elle est supprimée automatiquement avec la table au rollback
 * (`Schema::dropIfExists`), donc pas de `down()` dédié nécessaire.
 */
final class Check
{
    /**
     * Ajoute une contrainte CHECK `col IN (...valeurs)`.
     *
     * @param  list<string>  $values
     */
    public static function enum(string $table, string $column, array $values, ?string $constraint = null): void
    {
        $constraint ??= "{$table}_{$column}_check";
        $list = implode(', ', array_map(static fn (string $v): string => "'".str_replace("'", "''", $v)."'", $values));

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$column} IN ({$list}))");
    }

    /**
     * Ajoute une contrainte CHECK SQL libre (ex. `ends_at > starts_at`).
     */
    public static function raw(string $table, string $constraint, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
    }
}

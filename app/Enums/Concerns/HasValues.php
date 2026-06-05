<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Expose les valeurs d'un enum backed sous forme de tableau de strings.
 * Source unique pour les CHECK constraints des migrations et les casts modèles.
 */
trait HasValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

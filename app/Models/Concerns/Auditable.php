<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit log automatique sur les entités sensibles (CLAUDE.md §3.4) via
 * spatie/activitylog. Chaque modèle déclare la liste BLANCHE des attributs
 * suivis (`auditLogAttributes()`) — jamais de secrets/PII inutile (mots de
 * passe, secrets 2FA, IBAN, etc.).
 *
 * On ne logge que les changements effectifs (`logOnlyDirty`) et on ignore les
 * enregistrements vides (`dontLogEmptyChanges`).
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditLogAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Liste blanche des attributs audités pour ce modèle.
     *
     * @return list<string>
     */
    abstract protected function auditLogAttributes(): array;
}

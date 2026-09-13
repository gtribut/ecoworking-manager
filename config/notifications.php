<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Centre de notifications (PRD §3.8.4)
|--------------------------------------------------------------------------
|
| `retention_days` borne l'historique in-app conservé dans la table
| `notifications` (driver database). La commande `notifications:purge`,
| planifiée quotidiennement (routes/console.php), supprime au-delà —
| lues comme non lues : le PRD parle d'un historique borné, pas d'un
| nettoyage des seules lignes lues.
|
*/

return [
    'retention_days' => (int) env('NOTIFICATIONS_RETENTION_DAYS', 90),
];

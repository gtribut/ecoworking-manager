<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Purge de l'historique du centre de notifications (PRD §3.8.4, « historique
 * conservé 90 j configurable »). Supprime TOUTES les notifications plus
 * vieilles que la rétention, lues comme non lues. Lancée quotidiennement par
 * le scheduler ; `--days` permet un passage ponctuel avec une autre borne.
 */
final class PurgeNotificationsCommand extends Command
{
    protected $signature = 'notifications:purge {--days= : Rétention en jours (défaut : config notifications.retention_days)}';

    protected $description = 'Supprime les notifications in-app au-delà de la rétention configurée.';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('notifications.retention_days')));

        // Borne calculée en PHP puis comparée CÔTÉ SQL (convention du dépôt).
        // `notifications.created_at` est un timestamp SANS fuseau : l'écriture
        // et la lecture utilisent la même heure murale, le décalage Paris/UTC
        // de la session Postgres est donc sans effet ici.
        $boundary = now()->subDays($days);

        $deleted = DatabaseNotification::query()
            ->where('created_at', '<', $boundary)
            ->delete();

        $this->info("{$deleted} notification(s) supprimée(s) (rétention : {$days} jours).");

        return self::SUCCESS;
    }
}

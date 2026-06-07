<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base des notifications portail (C8). Centralise le routage des canaux selon
 * les préférences du destinataire (PRD §3.8.4) :
 *
 * - `notify_in_app` → canal `database` (centre de notifications persistant).
 * - `notify_email`  → canal `mail`, uniquement si la notification est marquée
 *   « doublée par email » (événements critiques : facture émise/retard,
 *   document à valider). Les toggles sont indépendants et activés par défaut.
 *
 * Toutes les notifications sont mises en queue (`ShouldQueue`) : l'envoi d'email
 * ne bloque jamais la requête métier (CLAUDE.md §7, anti-pattern « email dans le
 * controller »). En dev les jobs partent sur la queue Postgres (ADR-0007).
 *
 * Chaque notification concrète déclare `$emailable` (doublage email ou non) et
 * implémente `toDatabase()` ; `toMail()` n'est requis que si `$emailable`.
 */
abstract class PortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * La notification est-elle un événement critique doublé par email ?
     * Surchargée à `false` pour les notifications purement in-app.
     */
    protected bool $emailable = true;

    /**
     * Canaux effectifs = intersection des préférences du destinataire et des
     * canaux supportés par la notification. Un destinataire qui a tout coupé
     * ne reçoit rien (tableau vide → aucun envoi).
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->notify_in_app ?? true) {
            $channels[] = 'database';
        }

        if (($notifiable->notify_email ?? true) && $this->emailable) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Charge utile stockée sur la table `notifications` (driver database).
     *
     * @return array<string, mixed>
     */
    abstract public function toDatabase(object $notifiable): array;

    /**
     * URL absolue d'une page du portail membre (liens d'emails). `app.url` est
     * aligné sur `PORTAL_URL` en prod (BRIEF §13).
     */
    protected function portalUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementRegistrationStatus;
use App\Exceptions\DomainActionException;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Inscription / désinscription aux événements (C12.3, PRD §2.5 « s'inscrire
 * aux events », §4.11). Règles métier hors HTTP :
 *
 * - uniquement les annonces `event` avec `requires_registration` ;
 * - événement non commencé (pas de RSVP rétroactif) ;
 * - jauge `max_participants` respectée sous verrou (pas d'over-booking par
 *   inscriptions concurrentes) ;
 * - une ligne unique par (annonce, user) : une ré-inscription après annulation
 *   réactive la même ligne (contrainte UNIQUE en DB, historique conservé).
 */
final class AnnouncementRegistrationService
{
    public function __construct(private DatabaseManager $db) {}

    public function register(User $user, Announcement $announcement): AnnouncementRegistration
    {
        $this->assertOpenEvent($announcement);

        return $this->db->transaction(function () use ($user, $announcement): AnnouncementRegistration {
            // Verrou sur l'annonce : sérialise les inscriptions concurrentes
            // pour que le comptage de jauge ne puisse pas être dépassé.
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());

            $registration = $locked->registrations()->where('user_id', $user->id)->first();

            if ($registration?->status === AnnouncementRegistrationStatus::Registered) {
                throw new DomainActionException('Vous êtes déjà inscrit(e) à cet événement.');
            }

            if ($locked->max_participants !== null
                && $locked->activeRegistrations()->count() >= $locked->max_participants) {
                throw new DomainActionException('Cet événement est complet.');
            }

            if ($registration !== null) {
                // Ré-inscription après annulation : on réactive la même ligne.
                $registration->update([
                    'status' => AnnouncementRegistrationStatus::Registered->value,
                    'registered_at' => now(),
                ]);

                return $registration;
            }

            return $locked->registrations()->create([
                'user_id' => $user->id,
                'status' => AnnouncementRegistrationStatus::Registered->value,
                'registered_at' => now(),
            ]);
        });
    }

    /**
     * Annule l'inscription (statut `cancelled`, ligne conservée pour le suivi
     * admin PRD §4.11.3). L'appelant doit avoir vérifié la Policy `delete`.
     */
    public function cancel(AnnouncementRegistration $registration): void
    {
        $announcement = $registration->announcement;

        if ($announcement->event_starts_at !== null && $announcement->event_starts_at->isPast()) {
            throw new DomainActionException("Cet événement a déjà commencé : l'inscription ne peut plus être annulée.");
        }

        $registration->update(['status' => AnnouncementRegistrationStatus::Cancelled->value]);
    }

    /** Règles communes d'accès au RSVP (type, inscription requise, date). */
    private function assertOpenEvent(Announcement $announcement): void
    {
        if (! $announcement->acceptsRegistrations()) {
            throw new DomainActionException("Cette annonce n'accepte pas d'inscription.");
        }

        if ($announcement->event_starts_at !== null && $announcement->event_starts_at->isPast()) {
            throw new DomainActionException('Cet événement a déjà commencé.');
        }
    }
}

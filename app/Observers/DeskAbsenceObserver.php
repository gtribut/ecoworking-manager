<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\NotifiedAction;
use App\Models\DeskAbsence;
use App\Notifications\AbsenceRecordedNotification;
use App\Observers\Concerns\NotifiesOwnerOfAdminAction;

/**
 * Cycle de vie d'une absence (lot G, PRD §3.8.4 « absence enregistrée par
 * l'admin pour le résident »). Le résident est notifié dès qu'un TIERS touche
 * à ses absences — saisie, correction ou suppression depuis le back-office.
 * Quand il agit lui-même depuis le portail, rien ne part vers lui (la
 * notification symétrique vers les admins, Q25, reste dans PresenceController).
 *
 * Observer plutôt que Resource Filament : `DeskAbsenceResource` passe par
 * `PresenceService`, mais la suppression se fait par `DeleteAction` — un seul
 * point d'accroche couvre les trois chemins.
 */
final class DeskAbsenceObserver
{
    use NotifiesOwnerOfAdminAction;

    /** Champs métier visibles du résident (la note interne ne l'est pas). */
    private const array NOTIFIABLE_ATTRIBUTES = [
        'date_start', 'date_end', 'period', 'recurrence_type', 'recurrence_day_of_week',
    ];

    public function created(DeskAbsence $absence): void
    {
        $this->notifyOwner($absence, NotifiedAction::Created);
    }

    public function updated(DeskAbsence $absence): void
    {
        if ($absence->wasChanged(self::NOTIFIABLE_ATTRIBUTES)) {
            $this->notifyOwner($absence, NotifiedAction::Updated);
        }
    }

    public function deleted(DeskAbsence $absence): void
    {
        $this->notifyOwner($absence, NotifiedAction::Removed);
    }

    private function notifyOwner(DeskAbsence $absence, NotifiedAction $action): void
    {
        $this->ownerToNotify($absence->user)
            ?->notify(new AbsenceRecordedNotification($absence, $action));
    }
}

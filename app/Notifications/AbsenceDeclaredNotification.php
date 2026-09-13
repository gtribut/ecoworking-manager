<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Models\User;
use Throwable;

/**
 * Absence nomade déclarée par un résident via le module présence (PRD Q25) :
 * l'admin est notifié systématiquement pour avoir la visibilité sur les bureaux
 * libérés. Notification purement in-app (non critique → pas d'email).
 */
final class AbsenceDeclaredNotification extends PortalNotification
{
    protected bool $emailable = false;

    public function __construct(
        private readonly DeskAbsence $absence,
        private readonly User $resident,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'absence.declared',
            'absence_id' => $this->absence->id,
            'resident_id' => $this->resident->id,
            'resident_name' => $this->resident->fullName(),
            'message' => "{$this->resident->fullName()} a déclaré une absence.",
            'url' => $this->backOfficeUrl(),
        ];
    }

    /**
     * Lien vers la liste des absences du back-office (PRD §3.8.4). Cette
     * notification est destinée à un admin : `/admin` n'existe pas côté
     * portail (routes SPA de `App.tsx`), il n'y a pas de route symétrique à
     * proposer là-bas. `DeskAbsenceResource::getUrl()` suppose un panel
     * Filament résolu ; en file d'attente (`ShouldQueue`), sans contexte HTTP,
     * la résolution peut échouer selon l'environnement — on retombe alors sur
     * `null` (pas de lien) plutôt qu'une URL cassée.
     */
    private function backOfficeUrl(): ?string
    {
        try {
            return DeskAbsenceResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DeskAbsence;
use App\Models\User;

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
            'url' => '/admin',
        ];
    }
}

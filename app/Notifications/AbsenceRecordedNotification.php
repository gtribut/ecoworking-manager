<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\NotifiedAction;
use App\Models\DeskAbsence;

/**
 * Absence enregistrée, modifiée ou supprimée **par l'admin** sur le bureau d'un
 * résident (PRD §3.8.4, « absence enregistrée par l'admin pour le résident »).
 * In-app uniquement (non critique), comme la notification symétrique vers les
 * admins quand c'est le résident qui déclare (AbsenceDeclaredNotification, Q25).
 * Le ciblage (acteur ≠ propriétaire) est fait par DeskAbsenceObserver.
 *
 * Charge utile sans PII : dates et période, jamais la note de l'absence
 * (elle n'est déjà pas exposée au membre, cf. DeskAbsenceResource).
 */
final class AbsenceRecordedNotification extends PortalNotification
{
    protected bool $emailable = false;

    /**
     * Absence figée À LA CONSTRUCTION plutôt que portée en modèle : sur une
     * suppression, la ligne n'existe plus quand le worker traite la queue et
     * `SerializesModels` ne saurait pas la recharger.
     *
     * @var array{id: int|null, date_start: string|null, date_end: string|null, period: string|null, window: string}
     */
    private readonly array $absence;

    public function __construct(DeskAbsence $absence, private readonly NotifiedAction $action)
    {
        $this->absence = [
            'id' => $absence->id,
            'date_start' => $absence->date_start?->format('Y-m-d'),
            'date_end' => $absence->date_end?->format('Y-m-d'),
            'period' => $absence->period?->value,
            'window' => self::window($absence),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => "absence.{$this->action->value}",
            'absence_id' => $this->absence['id'],
            'date_start' => $this->absence['date_start'],
            'date_end' => $this->absence['date_end'],
            'period' => $this->absence['period'],
            'message' => $this->message(),
            'url' => '/presence',
        ];
    }

    private function message(): string
    {
        $window = $this->absence['window'];

        return match ($this->action) {
            NotifiedAction::Created => "L'accueil a enregistré une absence sur votre bureau {$window}.",
            NotifiedAction::Updated => "L'accueil a modifié une absence sur votre bureau : {$window}.",
            NotifiedAction::Removed => "L'accueil a supprimé votre absence {$window}.",
        };
    }

    /** Fenêtre en toutes lettres : jour unique, plage, ou récurrence hebdo. */
    private static function window(DeskAbsence $absence): string
    {
        $start = $absence->date_start?->format('d/m/Y') ?? '';
        $end = $absence->date_end?->format('d/m/Y');
        $period = $absence->period?->getLabel();

        $window = $absence->recurrence_type === DeskAbsenceRecurrence::Weekly
            ? "chaque semaine à partir du {$start}".($end === null ? '' : " et jusqu'au {$end}")
            : ($end === null || $end === $start ? "le {$start}" : "du {$start} au {$end}");

        return $period === null ? $window : "{$window} ({$period})";
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Exceptions\DomainActionException;
use App\Models\DeskAbsence;
use App\Models\User;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Présence nomade des résidents (C7.3, PRD §3.4.6 / data_model §4.3).
 *
 * Modèle implicite : un résident avec bureau attitré est présent par défaut les
 * jours ouvrés, SAUF absence déclarée. Les récurrences hebdomadaires sont
 * expansées À LA LECTURE (jamais pré-générées).
 */
final class PresenceService
{
    /**
     * Déclare une absence sur le bureau attitré du membre. L'admin peut déclarer
     * pour autrui ; un membre uniquement sur SON bureau.
     *
     * @param  array{
     *     user: User,
     *     date_start: CarbonInterface,
     *     date_end?: ?CarbonInterface,
     *     period?: Period,
     *     recurrence_type?: DeskAbsenceRecurrence,
     *     recurrence_day_of_week?: ?int,
     *     notes?: ?string,
     *     created_by?: ?int,
     * }  $data
     */
    public function declareAbsence(array $data): DeskAbsence
    {
        $user = $data['user'];
        $deskId = $user->memberProfile?->desk_id;

        if ($deskId === null) {
            throw new DomainActionException("Vous n'avez pas de bureau attitré : aucune absence à déclarer.");
        }

        $recurrence = $data['recurrence_type'] ?? DeskAbsenceRecurrence::None;

        return DeskAbsence::create([
            'desk_id' => $deskId,
            'user_id' => $user->id,
            'date_start' => $data['date_start']->format('Y-m-d'),
            'date_end' => isset($data['date_end']) ? $data['date_end']->format('Y-m-d') : null,
            'period' => ($data['period'] ?? Period::FullDay)->value,
            'recurrence_type' => $recurrence->value,
            'recurrence_day_of_week' => $recurrence === DeskAbsenceRecurrence::Weekly
                ? ($data['recurrence_day_of_week'] ?? null)
                : null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Le membre est-il présent (bureau attitré, jour ouvré, hors absence) sur
     * cette date / demi-journée ?
     */
    public function isPresent(User $user, CarbonInterface $date, Period $period = Period::FullDay): bool
    {
        if ($user->memberProfile?->desk_id === null) {
            return false;
        }

        return $this->presentOn($this->candidateAbsences($user), $date, $period);
    }

    /**
     * Calendrier de présence d'un membre sur une plage (jours ouvrés) :
     * liste des jours présents au format Y-m-d.
     *
     * Les absences sont chargées UNE seule fois pour toute la plage (l'itération
     * jour par jour ne refait aucune requête — ~90 requêtes économisées sur
     * 3 mois par rapport à un appel d'isPresent() par jour).
     *
     * @return list<string>
     */
    public function presentDays(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($user->memberProfile?->desk_id === null) {
            return [];
        }

        $absences = $this->candidateAbsences($user);

        $days = [];
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'));
        $end = CarbonImmutable::parse($to->format('Y-m-d'));

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($this->presentOn($absences, $cursor)) {
                $days[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Présent = jour ouvré ET aucune absence (ponctuelle, plage ou récurrente)
     * ne couvre le créneau.
     *
     * @param  Collection<int, DeskAbsence>  $absences
     */
    private function presentOn(Collection $absences, CarbonInterface $date, Period $period = Period::FullDay): bool
    {
        if (! FrenchHolidays::isWorkingDay($date)) {
            return false;
        }

        return ! $absences->contains(fn (DeskAbsence $absence): bool => $this->absenceCovers($absence, $date, $period));
    }

    /** @return Collection<int, DeskAbsence> */
    private function candidateAbsences(User $user): Collection
    {
        return $user->deskAbsences()->get();
    }

    private function absenceCovers(DeskAbsence $absence, CarbonInterface $date, Period $period): bool
    {
        if (! $this->periodOverlaps($absence->period, $period)) {
            return false;
        }

        $start = CarbonImmutable::parse($absence->date_start->format('Y-m-d'));
        $end = $absence->date_end !== null
            ? CarbonImmutable::parse($absence->date_end->format('Y-m-d'))
            : null;
        $target = CarbonImmutable::parse($date->format('Y-m-d'));

        if ($absence->recurrence_type === DeskAbsenceRecurrence::Weekly) {
            if ($target->lessThan($start) || ($end !== null && $target->greaterThan($end))) {
                return false;
            }

            return $target->dayOfWeek === $absence->recurrence_day_of_week;
        }

        $end ??= $start; // jour unique
        if ($target->lessThan($start) || $target->greaterThan($end)) {
            return false;
        }

        return true;
    }

    /** Une absence « journée » couvre toute demi-journée ; sinon égalité stricte. */
    private function periodOverlaps(Period $absencePeriod, Period $requested): bool
    {
        return $absencePeriod === Period::FullDay
            || $requested === Period::FullDay
            || $absencePeriod === $requested;
    }
}

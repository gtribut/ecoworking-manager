<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Vue « Occupation du jour » (C12.6, PRD §4.8.4) : synthèse par étage des
 * bureaux attitrés (résident présent / absent / pas d'info + usage par un
 * tiers) et des bureaux libres (external ou « Disponible »), capacité
 * restante et résas salles du jour.
 *
 * Perf : nombre de requêtes CONSTANT quelle que soit la taille du parc
 * (~49 bureaux). Les absences de TOUS les résidents sont chargées en UNE
 * requête puis évaluées en mémoire via PresenceService (finding M8 : jamais
 * une requête par bureau). Un test anti-régression verrouille ce comptage.
 */
final class DailyOccupancyService
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly DeskAvailabilityService $availability,
    ) {}

    /**
     * Statut d'un bureau attitré : `present` / `absent` (dérivé des SEULES
     * absences déclarées, PRD §3.4.6 — un week-end ou un férié sans absence
     * reste « présent », ré-acté 2026-09-17) ou `unknown` quand aucun résident
     * n'est rattaché au bureau (« pas d'info », PRD §4.8.4).
     *
     * `is_working_day` n'entre plus dans ce statut : il ne sert qu'à signaler
     * que les bureaux nomades ne sont pas réservables ce jour-là.
     *
     * @return array{
     *     date: CarbonImmutable,
     *     is_working_day: bool,
     *     floors: array<int, array{assigned: list<array<string, mixed>>, unassigned: list<array<string, mixed>>}>,
     *     available_desks_count: int,
     *     room_bookings: EloquentCollection<int, Booking>,
     * }
     */
    public function forDate(CarbonImmutable $date): array
    {
        $desks = Resource::query()
            ->ofType(ResourceType::Desk)
            ->active()
            ->with('assignedMemberProfile.user')
            ->orderBy('floor')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $absencesByUser = $this->absencesByUser($desks, $date);

        $occupationsByDesk = DeskOccupation::query()
            ->whereDate('date', $date->toDateString())
            ->where('status', DeskOccupationStatus::Present->value)
            ->with(['user', 'ticket'])
            ->orderBy('period')
            ->get()
            ->groupBy('desk_id');

        $floors = [];

        foreach ($desks as $desk) {
            $resident = $desk->assignedMemberProfile->first()?->user;
            $occupations = $occupationsByDesk->get($desk->id, EloquentCollection::make());
            $floor = (int) ($desk->floor ?? 0);
            $floors[$floor] ??= ['assigned' => [], 'unassigned' => []];

            if ($desk->assignment === ResourceAssignment::Unassigned) {
                $floors[$floor]['unassigned'][] = [
                    'desk' => $desk,
                    'occupations' => $occupations,
                ];

                continue;
            }

            $floors[$floor]['assigned'][] = [
                'desk' => $desk,
                'resident' => $resident,
                'status' => match (true) {
                    $resident === null => 'unknown',
                    $this->presence->presentGivenAbsences(
                        $absencesByUser->get($resident->id, EloquentCollection::make())->toBase(),
                        $date,
                    ) => 'present',
                    default => 'absent',
                },
                'occupations' => $occupations,
            ];
        }

        ksort($floors);

        return [
            'date' => $date,
            'is_working_day' => FrenchHolidays::isWorkingDay($date),
            'floors' => $floors,
            'available_desks_count' => $this->availability->availableCount($date, Period::FullDay),
            'room_bookings' => $this->roomBookingsQuery($date)->get(),
        ];
    }

    /**
     * Résas de salles (réunion + événementiel) confirmées chevauchant la
     * journée, chronologiques. Chevauchement réel [J 00:00, J+1 00:00) —
     * pas de whereDate qui raterait une résa à cheval sur minuit.
     *
     * @return Builder<Booking>
     */
    public function roomBookingsQuery(CarbonInterface $date): Builder
    {
        $dayStart = CarbonImmutable::parse($date->format('Y-m-d'));

        return Booking::query()
            ->confirmed()
            ->whereHas('resource', function (Builder $query): void {
                $query->whereIn('type', [ResourceType::MeetingRoom->value, ResourceType::EventRoom->value]);
            })
            ->where('starts_at', '<', $dayStart->addDay())
            ->where('ends_at', '>', $dayStart)
            ->with(['resource', 'user', 'ticket'])
            ->orderBy('starts_at');
    }

    /**
     * Occupations « bureaux nomades » (external, présentes) du jour — pour la
     * vue rapide « Aujourd'hui » du dashboard (PRD §4.1.2).
     *
     * @return Builder<DeskOccupation>
     */
    public function externalOccupationsQuery(CarbonInterface $date): Builder
    {
        return DeskOccupation::query()
            ->whereDate('date', $date->format('Y-m-d'))
            ->where('source', DeskOccupationSource::ExternalTicket->value)
            ->where('status', DeskOccupationStatus::Present->value)
            ->with(['desk', 'user', 'ticket'])
            ->orderBy('period');
    }

    /**
     * Absences candidates de TOUS les résidents en UNE requête, groupées par
     * user. Sur-approximation volontaire (toute absence dont la plage brute
     * englobe la date, récurrences ouvertes incluses) : le tri fin est fait
     * en mémoire par PresenceService::absenceCovers().
     *
     * @param  EloquentCollection<int, resource>  $desks
     * @return SupportCollection<int|string, EloquentCollection<int, DeskAbsence>>
     */
    private function absencesByUser(EloquentCollection $desks, CarbonImmutable $date): SupportCollection
    {
        $residentIds = $desks
            ->map(fn (Resource $desk): ?int => $desk->assignedMemberProfile->first()?->user_id)
            ->filter()
            ->unique()
            ->values();

        if ($residentIds->isEmpty()) {
            return collect();
        }

        return DeskAbsence::query()
            ->whereIn('user_id', $residentIds)
            ->whereDate('date_start', '<=', $date->toDateString())
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('date_end')
                    ->orWhereDate('date_end', '>=', $date->toDateString());
            })
            ->get()
            ->groupBy('user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Services\Profile\ProfilePhotoService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Occupation du jour des 49 bureaux pour le plan des étages (C12.5, PRD §3.7).
 *
 * Modèle de présence (PRD §6.1) :
 * - bureau attitré (résident/staff) : présent par défaut TOUS les jours,
 *   week-ends et fériés compris, sauf absence déclarée ({@see PresenceService},
 *   ré-acté 2026-09-17) ;
 * - bureau non attitré : occupé uniquement via une occupation external
 *   (ticket) « present » sur la date ;
 * - bureau hors service : jamais occupé.
 *
 * Visibilité (PRD §3.7.5) : les détails de l'occupant ne sortent QUE si son
 * profil est opt-in annuaire (`show_in_directory`). Sinon `visible: false`
 * sans aucune donnée personnelle (« Coworker (souhaite rester discret) »).
 *
 * Perf : nombre de requêtes CONSTANT quel que soit le nombre de bureaux
 * (3 requêtes : bureaux + absences + occupations) — jamais de requête par
 * bureau (finding review M8).
 */
final class FloorPlanService
{
    public function __construct(private readonly PresenceService $presence) {}

    /**
     * `is_working_day` reste exposé à titre informatif (il ne conditionne PLUS
     * la présence des bureaux attitrés) : il renseigne le portail sur les jours
     * où les bureaux nomades ne sont pas réservables ({@see DeskAvailabilityService}).
     *
     * @return array{date: string, is_working_day: bool, desks: list<array<string, mixed>>}
     */
    public function forDate(CarbonImmutable $date, User $viewer): array
    {
        $desks = Resource::query()
            ->ofType(ResourceType::Desk)
            ->active()
            ->with(['assignedMemberProfile.user', 'assignedMemberProfile.company'])
            ->orderBy('display_order')
            ->get();

        // Absences de TOUS les titulaires en une requête (expansion en mémoire).
        $assignedUserIds = $desks
            ->map(fn (Resource $desk): ?int => $desk->assignedMemberProfile->first()?->user_id)
            ->filter()
            ->values();

        $absencesByUser = DeskAbsence::query()
            ->whereIn('user_id', $assignedUserIds)
            ->get()
            ->groupBy('user_id');

        // Occupations external « présent » du jour, en une requête.
        $occupationsByDesk = DeskOccupation::query()
            ->whereDate('date', $date->format('Y-m-d'))
            ->where('source', DeskOccupationSource::ExternalTicket->value)
            ->where('status', DeskOccupationStatus::Present->value)
            ->with(['user.memberProfile.company'])
            ->get()
            ->groupBy('desk_id');

        $ownDeskId = $viewer->memberProfile?->desk_id;

        return [
            'date' => $date->format('Y-m-d'),
            'is_working_day' => FrenchHolidays::isWorkingDay($date),
            'desks' => $desks
                ->map(fn (Resource $desk): array => $this->deskState(
                    $desk,
                    $date,
                    $absencesByUser,
                    $occupationsByDesk->get($desk->id, new Collection),
                    $ownDeskId,
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int|string, Collection<int, DeskAbsence>>  $absencesByUser
     * @param  Collection<int, DeskOccupation>  $occupations
     * @return array<string, mixed>
     */
    private function deskState(
        Resource $desk,
        CarbonImmutable $date,
        Collection $absencesByUser,
        Collection $occupations,
        ?int $ownDeskId,
    ): array {
        $base = [
            'resource_id' => $desk->id,
            'svg_desk_id' => $desk->svg_desk_id,
            'name' => $desk->name,
            'floor' => $desk->floor,
            'assignment' => $desk->assignment?->value,
            'is_own' => $desk->id === $ownDeskId,
        ];

        if ($desk->is_out_of_service) {
            return $base + ['status' => 'out_of_service', 'occupant' => null];
        }

        $profile = $desk->assignedMemberProfile->first();

        if ($desk->assignment !== ResourceAssignment::Unassigned && $profile !== null) {
            $absences = $absencesByUser->get($profile->user_id, new Collection);
            $morning = $this->presence->presentGivenAbsences($absences, $date, Period::Morning);
            $afternoon = $this->presence->presentGivenAbsences($absences, $date, Period::Afternoon);

            return $base + [
                'status' => $this->status($morning, $afternoon, 'absent'),
                // Le titulaire reste identifié même absent (PRD §3.7.3 « Bureau
                // de X (absent) ») — mais uniquement s'il est opt-in annuaire.
                'occupant' => $this->occupantCard($profile->user, $profile),
            ];
        }

        // Bureau non attitré (ou attitré sans titulaire rattaché) : pool external.
        $morning = $occupations->contains(fn (DeskOccupation $o): bool => $o->period !== Period::Afternoon);
        $afternoon = $occupations->contains(fn (DeskOccupation $o): bool => $o->period !== Period::Morning);
        $first = $occupations->first();

        return $base + [
            'status' => $this->status($morning, $afternoon, 'free'),
            'occupant' => $first !== null
                ? $this->occupantCard($first->user, $first->user?->memberProfile)
                : null,
        ];
    }

    private function status(bool $morning, bool $afternoon, string $emptyStatus): string
    {
        return match (true) {
            $morning && $afternoon => 'present',
            $morning || $afternoon => 'partial',
            default => $emptyStatus,
        };
    }

    /**
     * Fiche occupant, UNIQUEMENT si opt-in annuaire — jamais d'email ni de
     * téléphone (pas d'opt-in dédié au MVP, PRD §3.7.4 « Contacter » = 🟡).
     *
     * @return array<string, mixed>
     */
    private function occupantCard(?User $user, ?MemberProfile $profile): array
    {
        if ($user === null || $profile === null || ! $profile->show_in_directory) {
            return ['visible' => false];
        }

        return [
            'visible' => true,
            'member_profile_id' => $profile->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'photo' => ProfilePhotoService::urls($profile->photo_path, $user->id),
            'job_title' => $profile->job_title,
            'bio' => $profile->bio,
            'interests' => $profile->interests,
            'linkedin_url' => $profile->linkedin_url,
            'website_url' => $profile->website_url,
            'company' => $profile->company?->name,
        ];
    }
}

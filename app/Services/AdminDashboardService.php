<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ResourceType;
use App\Models\Booking;
use App\Models\DeskAbsence;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\Subscription;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Requêtes du dashboard admin (C12.6, PRD §4.1.2) : KPIs, blocs d'alerte,
 * activité récente et vue rapide « aujourd'hui ». Toute la logique vit ici
 * (testée) — les widgets Filament ne font que présenter (CLAUDE.md §7).
 *
 * Les montants agrégés retournés en float sont destinés à l'AFFICHAGE
 * uniquement (jamais réinjectés dans un calcul de facturation, §3.6).
 */
final class AdminDashboardService
{
    /**
     * Amplitude d'ouverture retenue pour le taux d'occupation des salles
     * (heures / jour ouvré). Convention d'affichage MVP — les horaires fins
     * par salle (`opening_hours`) ne sont pas encore exploités ici.
     */
    private const float ROOM_OPEN_HOURS_PER_DAY = 10.0;

    public function activeMembersCount(): int
    {
        return MemberProfile::query()->active()->count();
    }

    public function activeSubscriptionsCount(): int
    {
        return Subscription::query()->active()->count();
    }

    /**
     * Factures en retard : count + montant total restant dû.
     *
     * @return array{count: int, amount_due: float}
     */
    public function overdueInvoicesStats(): array
    {
        $base = $this->overdueInvoicesQuery();

        return [
            'count' => (clone $base)->count(),
            'amount_due' => round(
                (float) (clone $base)->sum('total_ttc') - (float) (clone $base)->sum('amount_paid'),
                2,
            ),
        ];
    }

    /**
     * Factures émises, échues et non soldées — même prédicat que le cron
     * `invoices:update-overdue`, SANS dépendre du statut `overdue` seul
     * (une facture `sent` échue dont le cron n'est pas encore passé compte).
     * Ordonnées de la plus anciennement échue à la plus récente.
     *
     * @return Builder<Invoice>
     */
    public function overdueInvoicesQuery(): Builder
    {
        return Invoice::query()
            ->issued()
            ->where('is_credit_note', false)
            ->whereIn('status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', CarbonImmutable::today()->toDateString())
            ->whereColumn('amount_paid', '<', 'total_ttc')
            ->orderBy('due_at');
    }

    /**
     * CA TTC émis sur le mois civil de la date donnée. Les avoirs (montants
     * négatifs, cf. CancelInvoiceService) sont naturellement déduits ; les
     * brouillons (sans numéro) sont exclus. Affichage uniquement.
     */
    public function issuedRevenueForMonth(CarbonInterface $month): float
    {
        $start = CarbonImmutable::parse($month->format('Y-m-d'))->startOfMonth();

        return round((float) Invoice::query()
            ->issued()
            ->whereBetween('issued_at', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->sum('total_ttc'), 2);
    }

    /**
     * Taux d'occupation moyen des salles (réunion + événementiel) sur la
     * semaine civile de la date donnée : heures réservées confirmées /
     * (salles actives × jours ouvrés × {@see self::ROOM_OPEN_HOURS_PER_DAY}).
     *
     * @return float pourcentage 0..100, arrondi à 1 décimale
     */
    public function roomOccupancyRateForWeek(CarbonInterface $day): float
    {
        $start = CarbonImmutable::parse($day->format('Y-m-d'))->startOfWeek();
        $end = $start->endOfWeek();

        $roomIds = Resource::query()
            ->active()
            ->whereIn('type', [ResourceType::MeetingRoom->value, ResourceType::EventRoom->value])
            ->pluck('id');

        $workingDays = 0;
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            if (FrenchHolidays::isWorkingDay($cursor)) {
                $workingDays++;
            }
        }

        $capacityHours = $roomIds->count() * $workingDays * self::ROOM_OPEN_HOURS_PER_DAY;

        if ($capacityHours <= 0) {
            return 0.0;
        }

        // Chevauchement réel [lundi 00:00, lundi suivant 00:00) — pas de
        // whereDate qui raterait une résa à cheval sur minuit (review #10).
        $bookedHours = Booking::query()
            ->confirmed()
            ->whereIn('resource_id', $roomIds)
            ->where('starts_at', '<', $end->addSecond())
            ->where('ends_at', '>', $start)
            ->get(['starts_at', 'ends_at'])
            ->sum(fn (Booking $booking): float => $booking->starts_at->diffInMinutes($booking->ends_at) / 60);

        return round(min(100.0, $bookedHours / $capacityHours * 100), 1);
    }

    /**
     * Abonnements actifs se terminant dans les N prochains jours (PRD §4.1.2
     * « Abonnements qui se terminent dans les 30 prochains jours »).
     *
     * @return Builder<Subscription>
     */
    public function endingSubscriptionsQuery(int $days = 30): Builder
    {
        $today = CarbonImmutable::today();

        return Subscription::query()
            ->active()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->with(['offer', 'subscriber'])
            ->orderBy('ends_at');
    }

    /**
     * Nouveaux membres arrivés sur la semaine civile en cours (PRD §4.1.2
     * vue rapide « Aujourd'hui »).
     *
     * @return Builder<MemberProfile>
     */
    public function newMembersThisWeekQuery(): Builder
    {
        $start = CarbonImmutable::today()->startOfWeek();

        return MemberProfile::query()
            ->whereBetween('arrival_date', [$start->toDateString(), $start->endOfWeek()->toDateString()])
            ->with(['user', 'company'])
            ->orderByDesc('arrival_date');
    }

    /**
     * Conformité des documents internes actifs publiés : combien de membres
     * ACTIFS ont validé la version courante de chaque document (PRD §4.1.2
     * « Documents internes non validés par X% des membres »).
     *
     * @return Collection<int, array{document: InternalDocument, validated: int, total: int, rate: float}>
     */
    public function internalDocumentValidationStats(): Collection
    {
        $activeMemberUserIds = MemberProfile::query()->active()->pluck('user_id');
        $total = $activeMemberUserIds->count();

        return InternalDocument::query()
            ->active()
            ->whereNotNull('published_at')
            ->withCount([
                'validations as validated_count' => function (Builder $query) use ($activeMemberUserIds): void {
                    $query
                        ->whereColumn('member_document_validations.version', 'internal_documents.version')
                        ->whereIn('user_id', $activeMemberUserIds);
                },
            ])
            ->orderBy('title')
            ->get()
            ->map(fn (InternalDocument $document): array => [
                'document' => $document,
                'validated' => (int) $document->getAttribute('validated_count'),
                'total' => $total,
                'rate' => $total > 0
                    ? round((int) $document->getAttribute('validated_count') / $total * 100, 1)
                    : 0.0,
            ])
            ->values();
    }

    /**
     * Absences que les membres viennent de déclarer DEPUIS LE PORTAIL (PRD Q25,
     * visibilité de l'admin sur les bureaux libérés). Une notification in-app
     * part déjà vers les admins, mais le back-office n'a pas de cloche pour
     * l'afficher (tranché 2026-09-20) : ce bloc du dashboard EST la surface de
     * restitution. Saisie depuis le back-office (`created_by` ≠ titulaire)
     * exclue : l'admin n'a pas à être notifié de sa propre saisie.
     *
     * @return Builder<DeskAbsence>
     */
    public function recentPortalAbsencesQuery(int $days = 14, int $limit = 5): Builder
    {
        return DeskAbsence::query()
            ->whereColumn('created_by', 'user_id')
            ->where('created_at', '>=', CarbonImmutable::now()->subDays($days))
            ->with(['user', 'desk'])
            ->latest('created_at')
            ->limit($limit);
    }

    /**
     * Dernières entrées de l'audit log (PRD §4.1.2 « Activité récente »).
     *
     * @return Builder<Activity>
     */
    public function recentActivityQuery(int $limit = 10): Builder
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->latest('id')
            ->limit($limit);
    }
}

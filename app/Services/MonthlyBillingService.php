<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLineSubscription;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Facturation mensuelle récurrente des abonnements (C6.5, PRD §5.1).
 *
 * Règle d'or (figée 2026-06-07) : la récurrente produit **une facture PAR
 * ENTITÉ** (`billable`), jamais une par abonnement. Tous les abonnements actifs
 * d'une même entité sur la période sont **consolidés** : une ligne par
 * prestation (= même offre, même période/prorata), `quantity` = nb d'abos
 * regroupés. Le détail abo-par-abo (période + quote-part HT) vit dans
 * `invoice_line_subscriptions` ; sur une ligne regroupée `related` reste NULL.
 *
 * Idempotente : clé (entité, période). Un re-déclenchement (cron, instant T,
 * manuel) ne recrée pas une facture déjà produite. Backstop DB : UNIQUE
 * (subscription_id, period_start, period_end) sur la table de liaison.
 *
 * Prix relu du CATALOGUE COURANT (§3.6 : pas de prix figé chez l'abonné),
 * modulé par la remise de l'entité (§6.4), proraté aux jours consommés (bornes
 * incluses, ROUND_HALF_UP). Produit des BROUILLONS : l'émission (numéro, PDF)
 * reste un acte explicite via {@see IssueInvoiceService}.
 */
final class MonthlyBillingService
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Génère les brouillons du mois — une facture par entité concernée.
     *
     * @return Collection<int, Invoice> factures créées (hors entités déjà facturées)
     */
    public function generateMonth(CarbonImmutable $month): Collection
    {
        $periodStart = $month->startOfMonth();
        $periodEnd = $month->endOfMonth();

        $created = new Collection;

        // Entités distinctes ayant ≥ 1 abonnement actif facturable sur la période.
        $this->billableSubscriptions($periodStart, $periodEnd)
            ->select('billable_type', 'billable_id')
            ->distinct()
            ->get()
            ->each(function (Subscription $entity) use ($periodStart, $periodEnd, $created): void {
                $invoice = $this->generateForEntity(
                    $entity->billable_type,
                    (int) $entity->billable_id,
                    $periodStart,
                    $periodEnd,
                );
                if ($invoice !== null) {
                    $created->push($invoice);
                }
            });

        return $created;
    }

    /**
     * Génère (ou retrouve) le brouillon récurrent d'une entité pour une période :
     * tous ses abonnements actifs sont consolidés en une facture, lignes
     * regroupées par prestation. Retourne null si l'entité est déjà facturée sur
     * la période (idempotence) ou n'a aucun abonnement facturable.
     */
    public function generateForEntity(string $billableType, int $billableId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?Invoice
    {
        $subscriptions = $this->billableSubscriptions($periodStart, $periodEnd)
            ->where('billable_type', $billableType)
            ->where('billable_id', $billableId)
            ->with(['offer', 'billable'])
            ->get();

        if ($subscriptions->isEmpty()) {
            return null;
        }

        if ($this->alreadyBilled($subscriptions->pluck('id')->all(), $periodStart, $periodEnd)) {
            return null;
        }

        return $this->buildInvoice($billableType, $billableId, $subscriptions, $periodStart, $periodEnd);
    }

    /**
     * Génère le brouillon d'un seul abonnement (facturation « instant T » d'un
     * démarrage en cours de mois). La ligne produite reste une ligne récurrente
     * regroupée (de cardinalité 1) : `related` NULL + liaison tracée. Retourne
     * null si cet abonnement est déjà facturé sur la période.
     */
    public function generateForSubscription(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?Invoice
    {
        if ($this->alreadyBilled([$subscription->id], $periodStart, $periodEnd)) {
            return null;
        }

        $subscription->loadMissing(['offer', 'billable']);
        if ($subscription->offer === null) {
            return null;
        }

        return $this->buildInvoice(
            $subscription->billable_type,
            (int) $subscription->billable_id,
            new Collection([$subscription]),
            $periodStart,
            $periodEnd,
        );
    }

    /**
     * Construit la facture : regroupe les abonnements par prestation (offre +
     * période effective), crée une ligne par groupe (× quantité) et la liaison
     * de traçabilité. Transactionnel.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function buildInvoice(string $billableType, int $billableId, Collection $subscriptions, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?Invoice
    {
        // Plan par abonnement (jours consommés, prorata, prix courant) ; on écarte
        // les abos sans offre ou sans jour consommé sur la période.
        $plans = $subscriptions
            ->map(fn (Subscription $sub): ?array => $this->planFor($sub, $periodStart, $periodEnd))
            ->filter()
            ->values();

        if ($plans->isEmpty()) {
            return null;
        }

        // Regroupement par prestation : même offre + même période effective
        // (un prorata distinct = un groupe distinct, donc une ligne séparée).
        $groups = $plans->groupBy(
            fn (array $p): string => $p['offer_id'].'|'.$p['period_start']->toDateString().'|'.$p['period_end']->toDateString(),
        );

        return $this->db->transaction(function () use ($billableType, $billableId, $groups): Invoice {
            $invoice = Invoice::create([
                'billable_type' => $billableType,
                'billable_id' => $billableId,
                'status' => InvoiceStatus::Draft->value,
            ]);

            $subtotalHt = 0.0;
            $totalVat = 0.0;
            $totalTtc = 0.0;

            foreach ($groups as $group) {
                $first = $group->first();
                $quantity = $group->count();

                $totals = InvoiceLineCalculator::totals([
                    'quantity' => $quantity,
                    'unit_price_ht' => $first['unit_price_ht'],
                    'discount_rate' => $first['discount_rate'],
                    'vat_rate' => $first['vat_rate'],
                ]);

                $line = $invoice->lines()->create([
                    'related_type' => null, // ligne regroupée : détail dans la liaison
                    'related_id' => null,
                    'description' => $this->lineLabel($first),
                    'quantity' => number_format($quantity, 2, '.', ''),
                    'unit_price_ht' => number_format($first['unit_price_ht'], 2, '.', ''),
                    'discount_rate' => $first['discount_rate'] > 0 ? number_format($first['discount_rate'], 2, '.', '') : null,
                    'vat_rate' => number_format($first['vat_rate'], 2, '.', ''),
                    'line_total_ht' => $totals['line_total_ht'],
                    'line_vat' => $totals['line_vat'],
                    'line_total_ttc' => $totals['line_total_ttc'],
                    'period_start' => $first['period_start']->toDateString(),
                    'period_end' => $first['period_end']->toDateString(),
                ]);

                // Quote-part HT par abonnement (remise + prorata inclus), figée.
                $quotePartHt = round($first['unit_price_ht'] * (1 - $first['discount_rate'] / 100), 2);
                foreach ($group as $plan) {
                    InvoiceLineSubscription::create([
                        'invoice_line_id' => $line->id,
                        'subscription_id' => $plan['subscription_id'],
                        'period_start' => $plan['period_start']->toDateString(),
                        'period_end' => $plan['period_end']->toDateString(),
                        'amount_ht' => number_format($quotePartHt, 2, '.', ''),
                    ]);
                }

                $subtotalHt += (float) $totals['line_total_ht'];
                $totalVat += (float) $totals['line_vat'];
                $totalTtc += (float) $totals['line_total_ttc'];
            }

            $invoice->forceFill([
                'subtotal_ht' => number_format($subtotalHt, 2, '.', ''),
                'total_vat' => number_format($totalVat, 2, '.', ''),
                'total_ttc' => number_format($totalTtc, 2, '.', ''),
            ])->save();

            return $invoice;
        });
    }

    /**
     * Calcule le plan de facturation d'un abonnement sur la période : période
     * effective (bornes incluses), jours consommés, prix unitaire courant proraté
     * et remise entité. Retourne null si offre absente ou aucun jour consommé.
     *
     * @return array{subscription_id: int, offer_id: int, offer_name: string, period_start: CarbonImmutable, period_end: CarbonImmutable, consumed_days: int, month_days: int, unit_price_ht: float, discount_rate: float, vat_rate: float}|null
     */
    private function planFor(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?array
    {
        $offer = $subscription->offer;
        if ($offer === null) {
            return null;
        }

        $effectiveStart = CarbonImmutable::parse($subscription->starts_at->toDateString())->max($periodStart);
        $effectiveEnd = $subscription->ends_at !== null
            ? CarbonImmutable::parse($subscription->ends_at->toDateString())->min($periodEnd)
            : $periodEnd;

        if ($effectiveStart->greaterThan($effectiveEnd)) {
            return null;
        }

        $monthDays = $periodEnd->day; // nb de jours du mois (28..31)
        $consumedDays = $effectiveStart->diffInDays($effectiveEnd) + 1; // bornes incluses

        $monthlyHt = (float) $offer->unit_price_ht;
        $unitPriceHt = $consumedDays < $monthDays
            ? round($monthlyHt * $consumedDays / $monthDays, 2)
            : round($monthlyHt, 2);

        return [
            'subscription_id' => (int) $subscription->id,
            'offer_id' => (int) $offer->id,
            'offer_name' => (string) $offer->name,
            'period_start' => $effectiveStart,
            'period_end' => $effectiveEnd,
            'consumed_days' => $consumedDays,
            'month_days' => $monthDays,
            'unit_price_ht' => $unitPriceHt,
            'discount_rate' => $this->entityDiscountRate($subscription),
            'vat_rate' => (float) $offer->vat_rate,
        ];
    }

    /** Libellé figé d'une ligne regroupée (prestation + mois, + mention prorata). */
    private function lineLabel(array $plan): string
    {
        $label = sprintf('%s — %s', $plan['offer_name'], $plan['period_start']->translatedFormat('F Y'));
        if ($plan['consumed_days'] < $plan['month_days']) {
            $label .= sprintf(' (prorata %d/%d j)', $plan['consumed_days'], $plan['month_days']);
        }

        return $label;
    }

    /**
     * Abonnements actifs facturables chevauchant la période (query réutilisable).
     *
     * @return Builder<Subscription>
     */
    private function billableSubscriptions(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Builder
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereDate('starts_at', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $periodStart->toDateString());
            });
    }

    /**
     * Idempotence : l'un de ces abonnements est-il déjà facturé sur le mois ?
     * (backstop applicatif de la clé (entité, période) au grain abonnement).
     *
     * @param  list<int>  $subscriptionIds
     */
    private function alreadyBilled(array $subscriptionIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): bool
    {
        return InvoiceLineSubscription::query()
            ->whereIn('subscription_id', $subscriptionIds)
            ->whereDate('period_start', '>=', $periodStart->toDateString())
            ->whereDate('period_end', '<=', $periodEnd->toDateString())
            ->exists();
    }

    /** Remise négociée de l'entité facturée (§6.4), 0 si non applicable. */
    private function entityDiscountRate(Subscription $subscription): float
    {
        $billable = $subscription->billable;

        return $billable instanceof Company ? (float) $billable->discount_rate : 0.0;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Facturation mensuelle récurrente des abonnements (C6.5, PRD §5.1).
 *
 * Idempotente : pour un abonnement + une période donnés, ne crée jamais deux
 * fois la même ligne/facture — un re-déclenchement (cron, instant, manuel)
 * retourne le brouillon existant. Le prix est relu du CATALOGUE COURANT (§3.6 :
 * pas de prix figé chez l'abonné), modulé par la remise de l'entité (§6.4), et
 * proraté aux jours consommés (bornes incluses, ROUND_HALF_UP).
 *
 * Produit des BROUILLONS : l'émission (numéro, PDF) reste un acte explicite
 * via {@see IssueInvoiceService}.
 */
final class MonthlyBillingService
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Génère les brouillons du mois pour tous les abonnements actifs concernés.
     *
     * @return Collection<int, Invoice> factures créées (hors abonnements déjà facturés)
     */
    public function generateMonth(CarbonImmutable $month): Collection
    {
        $periodStart = $month->startOfMonth();
        $periodEnd = $month->endOfMonth();

        $created = new Collection;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereDate('starts_at', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $periodStart->toDateString());
            })
            ->with(['offer', 'billable'])
            ->chunkById(100, function (Collection $subscriptions) use ($periodStart, $periodEnd, $created): void {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->generateForSubscription($subscription, $periodStart, $periodEnd);
                    if ($invoice !== null) {
                        $created->push($invoice);
                    }
                }
            });

        return $created;
    }

    /**
     * Génère (ou retrouve) le brouillon d'un abonnement pour une période.
     * Retourne null si déjà facturé (idempotence) — la facture existante n'est
     * pas dupliquée.
     */
    public function generateForSubscription(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?Invoice
    {
        if ($this->alreadyBilled($subscription, $periodStart)) {
            return null;
        }

        $offer = $subscription->offer;
        if ($offer === null) {
            return null;
        }

        [$consumedDays, $monthDays] = $this->consumedDays($subscription, $periodStart, $periodEnd);
        if ($consumedDays <= 0) {
            return null;
        }

        $monthlyHt = (float) $offer->unit_price_ht;
        $proratedHt = $consumedDays < $monthDays
            ? round($monthlyHt * $consumedDays / $monthDays, 2)
            : round($monthlyHt, 2);

        $discountRate = $this->entityDiscountRate($subscription);
        $vatRate = (float) $offer->vat_rate;

        $totals = InvoiceLineCalculator::totals([
            'quantity' => 1,
            'unit_price_ht' => $proratedHt,
            'discount_rate' => $discountRate,
            'vat_rate' => $vatRate,
        ]);

        return $this->db->transaction(function () use ($subscription, $offer, $periodStart, $periodEnd, $proratedHt, $discountRate, $vatRate, $totals, $consumedDays, $monthDays): Invoice {
            $invoice = Invoice::create([
                'billable_type' => $subscription->billable_type,
                'billable_id' => $subscription->billable_id,
                'status' => InvoiceStatus::Draft->value,
            ]);

            $label = sprintf('%s — %s', $offer->name, $periodStart->translatedFormat('F Y'));
            if ($consumedDays < $monthDays) {
                $label .= sprintf(' (prorata %d/%d j)', $consumedDays, $monthDays);
            }

            $invoice->lines()->create([
                'related_type' => $subscription->getMorphClass(),
                'related_id' => $subscription->getKey(),
                'description' => $label,
                'quantity' => 1,
                'unit_price_ht' => number_format($proratedHt, 2, '.', ''),
                'discount_rate' => $discountRate > 0 ? number_format($discountRate, 2, '.', '') : null,
                'vat_rate' => number_format($vatRate, 2, '.', ''),
                'line_total_ht' => $totals['line_total_ht'],
                'line_vat' => $totals['line_vat'],
                'line_total_ttc' => $totals['line_total_ttc'],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ]);

            // Totaux du brouillon (figés seulement à l'émission, mais cohérents ici).
            $invoice->forceFill([
                'subtotal_ht' => $totals['line_total_ht'],
                'total_vat' => $totals['line_vat'],
                'total_ttc' => $totals['line_total_ttc'],
            ])->save();

            return $invoice;
        });
    }

    /** Une ligne pour cet abonnement et ce mois existe-t-elle déjà ? */
    private function alreadyBilled(Subscription $subscription, CarbonImmutable $periodStart): bool
    {
        return InvoiceLine::query()
            ->where('related_type', $subscription->getMorphClass())
            ->where('related_id', $subscription->getKey())
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();
    }

    /**
     * Jours consommés (bornes incluses) = chevauchement de [starts_at, ends_at]
     * avec la période, et nombre de jours du mois.
     *
     * @return array{0: int, 1: int}
     */
    private function consumedDays(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $start = CarbonImmutable::parse($subscription->starts_at->toDateString())->max($periodStart);
        $end = $subscription->ends_at !== null
            ? CarbonImmutable::parse($subscription->ends_at->toDateString())->min($periodEnd)
            : $periodEnd;

        $monthDays = $periodEnd->day; // nb de jours du mois
        $consumed = $start->greaterThan($end) ? 0 : $start->diffInDays($end) + 1; // bornes incluses

        return [$consumed, $monthDays];
    }

    /** Remise négociée de l'entité facturée (§6.4), 0 si non applicable. */
    private function entityDiscountRate(Subscription $subscription): float
    {
        $billable = $subscription->billable;

        return $billable instanceof Company ? (float) $billable->discount_rate : 0.0;
    }
}

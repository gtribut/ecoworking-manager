<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Calcul HT/TVA/TTC d'une ligne de facture, toujours côté back (CLAUDE.md §3.6 :
 * jamais faire confiance au front). Arrondi à 2 décimales. La remise est un
 * pourcentage appliqué au sous-total HT avant TVA.
 */
final class InvoiceLineCalculator
{
    /**
     * @param  array<string, mixed>  $line  champs saisis (quantity, unit_price_ht, discount_rate, vat_rate)
     * @return array{line_total_ht: string, line_vat: string, line_total_ttc: string}
     */
    public static function totals(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 1);
        $unitPriceHt = (float) ($line['unit_price_ht'] ?? 0);
        $discountRate = (float) ($line['discount_rate'] ?? 0);
        $vatRate = (float) ($line['vat_rate'] ?? 0);

        $grossHt = $quantity * $unitPriceHt;
        $totalHt = round($grossHt * (1 - $discountRate / 100), 2);
        $vat = round($totalHt * $vatRate / 100, 2);
        $totalTtc = round($totalHt + $vat, 2);

        return [
            'line_total_ht' => number_format($totalHt, 2, '.', ''),
            'line_vat' => number_format($vat, 2, '.', ''),
            'line_total_ttc' => number_format($totalTtc, 2, '.', ''),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 *
 * Montants figés et cohérents entre eux (HT après remise, TVA, TTC).
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPriceHt = fake()->randomFloat(2, 10, 300);
        $vatRate = 20.00;

        $lineTotalHt = round($quantity * $unitPriceHt, 2);
        $lineVat = round($lineTotalHt * $vatRate / 100, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price_ht' => $unitPriceHt,
            'vat_rate' => $vatRate,
            'line_total_ht' => $lineTotalHt,
            'line_vat' => $lineVat,
            'line_total_ttc' => round($lineTotalHt + $lineVat, 2),
        ];
    }
}

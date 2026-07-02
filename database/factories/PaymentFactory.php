<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->issued(),
            // Montant corrélé à la facture liée : solde exact du total TTC
            // (review 06 mineur 6). Fallback plausible si la facture n'a pas
            // encore de totaux (brouillon à 0) ou est absente.
            'amount' => function (array $attributes) {
                $total = Invoice::query()->find($attributes['invoice_id'])?->total_ttc;

                return $total !== null && (float) $total > 0
                    ? $total
                    : fake()->randomFloat(2, 10, 500);
            },
            'paid_at' => now()->toDateString(),
            'method' => fake()->randomElement(PaymentMethod::values()),
        ];
    }

    public function sepa(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Sepa->value,
        ]);
    }
}

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
            'amount' => fake()->randomFloat(2, 10, 500),
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

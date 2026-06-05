<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketType;
use App\Models\Offer;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 *
 * Prix snapshoté à l'achat (figé), contrairement aux subscriptions.
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory()->pack(),
            'user_id' => User::factory(),
            'ticket_type' => TicketType::DeskHalfDay->value,
            'quantity' => 10,
            'unit_price_ht' => fake()->randomFloat(2, 5, 30),
            'vat_rate' => 20.00,
            'purchased_at' => now(),
        ];
    }

    /** Facturé sur une entité (billable poly). */
    public function billableToCompany(int $companyId): static
    {
        return $this->state(fn (array $attributes) => [
            'billable_type' => 'company',
            'billable_id' => $companyId,
        ]);
    }
}

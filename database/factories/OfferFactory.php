<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use App\Enums\TicketType;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('OFF-####')),
            'name' => fake()->words(3, true),
            'type' => OfferType::OneShot->value,
            'subscriber_kind' => SubscriberKind::Member->value,
            'unit_price_ht' => fake()->randomFloat(2, 5, 500),
            'vat_rate' => 20.00,
            'quantity_per_purchase' => 1,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    /** Abonnement récurrent mensuel. */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OfferType::Subscription->value,
            'billing_period' => BillingPeriod::Monthly->value,
        ]);
    }

    /** Pack de tickets (SKU distinct). */
    public function pack(int $quantity = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OfferType::Pack->value,
            'ticket_type' => TicketType::DeskHalfDay->value,
            'quantity_per_purchase' => $quantity,
        ]);
    }

    /** Domiciliation (souscripteur = entité). */
    public function domiciliation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OfferType::Subscription->value,
            'subscriber_kind' => SubscriberKind::Entity->value,
            'billing_period' => BillingPeriod::Monthly->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

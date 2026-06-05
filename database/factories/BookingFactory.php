<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 *
 * Réservation de salle confirmée. Chaque instance crée sa propre salle pour
 * éviter de heurter la contrainte d'exclusion GiST (anti-double-booking §6.8).
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+30 days')->setTime(
            fake()->numberBetween(8, 16),
            0
        );
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'resource_id' => Resource::factory()->meetingRoom(),
            'user_id' => User::factory(),
            'title' => fake()->optional()->sentence(3),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Confirmed->value,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => fake()->sentence(),
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
            'user_id' => null,
        ]);
    }

    /** Réservation payante (prix snapshoté). */
    public function paid(float $priceHt = 60.0): static
    {
        return $this->state(fn (array $attributes) => [
            'price_ht' => $priceHt,
            'vat_rate' => 20.00,
        ]);
    }
}

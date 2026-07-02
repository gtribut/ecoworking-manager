<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AnnouncementType::Info->value,
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
            'visibility' => Audience::All->value,
            'status' => AnnouncementStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnnouncementStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    /** Événement avec inscription. */
    public function event(): static
    {
        // Dates tirées DANS la closure : chaque instance d'un count(n) reçoit
        // son propre créneau (sinon la même date figée pour tout le lot).
        return $this->state(function (array $attributes) {
            $start = fake()->dateTimeBetween('+1 day', '+30 days');

            return [
                'type' => AnnouncementType::Event->value,
                'event_starts_at' => $start,
                'event_ends_at' => (clone $start)->modify('+2 hours'),
                'location' => fake()->address(),
                'requires_registration' => true,
                'max_participants' => fake()->numberBetween(10, 50),
            ];
        });
    }
}

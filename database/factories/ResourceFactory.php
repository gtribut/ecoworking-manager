<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<resource>
 *
 * Par défaut : un bureau (type le plus nombreux, 48). États dédiés pour les
 * salles. `assignment` n'a de sens que pour les bureaux (NULL sinon).
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ResourceType::Desk->value,
            'name' => 'Bureau '.fake()->unique()->numberBetween(1, 9999),
            'assignment' => ResourceAssignment::Unassigned->value,
            'floor' => fake()->numberBetween(0, 3),
            'svg_desk_id' => fake()->unique()->bothify('desk-###'),
            'is_active' => true,
        ];
    }

    public function desk(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Desk->value,
            'assignment' => ResourceAssignment::Unassigned->value,
        ]);
    }

    /** Bureau attitré d'un résident. */
    public function assignedResident(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Desk->value,
            'assignment' => ResourceAssignment::AssignedResident->value,
        ]);
    }

    public function meetingRoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::MeetingRoom->value,
            'name' => 'Salle '.fake()->unique()->word(),
            'assignment' => null,
            'svg_desk_id' => null,
            'capacity' => fake()->numberBetween(4, 12),
            'external_half_day_price_ht' => fake()->randomFloat(2, 30, 120),
        ]);
    }

    public function eventRoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::EventRoom->value,
            'name' => 'Espace événementiel',
            'assignment' => null,
            'svg_desk_id' => null,
            'capacity' => fake()->numberBetween(30, 80),
        ]);
    }

    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_out_of_service' => true,
        ]);
    }
}

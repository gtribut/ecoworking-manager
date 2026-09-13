<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeskOccupation>
 *
 * Par défaut : occupation d'un bureau non attribué par un ticket external.
 */
class DeskOccupationFactory extends Factory
{
    protected $model = DeskOccupation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'desk_id' => Resource::factory()->desk(),
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'period' => fake()->randomElement([Period::Morning->value, Period::Afternoon->value]),
            'source' => DeskOccupationSource::ExternalTicket->value,
            'status' => DeskOccupationStatus::Present->value,
        ];
    }

    /** Présence par défaut d'un résident (rarement matérialisée). */
    public function residentDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => DeskOccupationSource::ResidentDefault->value,
            'period' => Period::FullDay->value,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeskOccupationStatus::Absent->value,
        ]);
    }

    /** Occupation annulée (délai respecté ou super-pouvoir admin). */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeskOccupationStatus::Cancelled->value,
        ]);
    }
}

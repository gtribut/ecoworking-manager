<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Models\DeskAbsence;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeskAbsence>
 */
class DeskAbsenceFactory extends Factory
{
    protected $model = DeskAbsence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'desk_id' => Resource::factory()->assignedResident(),
            'user_id' => User::factory(),
            'date_start' => fake()->dateTimeBetween('now', '+15 days')->format('Y-m-d'),
            'period' => Period::FullDay->value,
            'recurrence_type' => DeskAbsenceRecurrence::None->value,
        ];
    }

    /** Absence récurrente hebdomadaire (ex. télétravail le vendredi). */
    public function weekly(int $dayOfWeek = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'recurrence_type' => DeskAbsenceRecurrence::Weekly->value,
            'recurrence_day_of_week' => $dayOfWeek,
        ]);
    }
}

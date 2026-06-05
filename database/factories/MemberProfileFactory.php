<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MemberProfileStatus;
use App\Models\Company;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberProfile>
 */
class MemberProfileFactory extends Factory
{
    protected $model = MemberProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'status' => MemberProfileStatus::Active->value,
            'arrival_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'job_title' => fake()->jobTitle(),
            'bio' => fake()->optional()->paragraph(),
            'show_in_directory' => fake()->boolean(70),
            'newsletter_opt_in' => fake()->boolean(),
        ];
    }

    /** Visible dans l'annuaire (opt-in). */
    public function inDirectory(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_in_directory' => true,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberProfileStatus::Paused->value,
        ]);
    }

    /** Rattaché au bureau attitré fourni (resource desk). */
    public function withDesk(int $deskId): static
    {
        return $this->state(fn (array $attributes) => [
            'desk_id' => $deskId,
        ]);
    }
}

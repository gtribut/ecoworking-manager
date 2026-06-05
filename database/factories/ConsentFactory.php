<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['newsletter', 'directory', 'image_rights', 'cgu']),
            'granted' => true,
            'granted_at' => now(),
            'source' => 'portal',
            'ip_address' => fake()->ipv4(),
        ];
    }

    /** Consentement révoqué. */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'granted' => false,
            'revoked_at' => now(),
        ]);
    }
}

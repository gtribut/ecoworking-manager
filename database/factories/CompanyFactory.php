<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => CompanyType::Company->value,
            'status' => CompanyStatus::Active->value,
            'legal_name' => fake()->company(),
            'legal_form' => fake()->randomElement(['SAS', 'SARL', 'SCOP', 'EURL']),
            'siret' => fake()->numerify('##############'),
            'billing_email' => fake()->companyEmail(),
            'address_line1' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'FR',
        ];
    }

    /** Particulier (entity_type = individual). */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => CompanyType::Individual->value,
            'legal_name' => null,
            'legal_form' => null,
            'siret' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Inactive->value,
        ]);
    }

    /** Avec remise négociée (PRD §6.4). */
    public function withDiscount(float $rate = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_rate' => $rate,
            'discount_scope' => 'all',
        ]);
    }
}

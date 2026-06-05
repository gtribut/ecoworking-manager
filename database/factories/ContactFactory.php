<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactRole;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'role' => fake()->randomElement(ContactRole::values()),
            'is_primary' => false,
        ];
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ContactRole::Billing->value,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}

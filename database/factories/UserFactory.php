<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assigne un rôle Spatie après création. Crée le rôle au besoin (guard `web`)
     * pour rester autonome en test sans dépendre du RoleSeeder.
     */
    public function withRole(RoleEnum $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            Role::findOrCreate($role->value, 'web');
            $user->assignRole($role->value);
        });
    }

    /** Membre « courant » du coworking (rôle résident par défaut). */
    public function member(): static
    {
        return $this->withRole(RoleEnum::Resident);
    }

    public function admin(): static
    {
        return $this->withRole(RoleEnum::Admin);
    }

    public function resident(): static
    {
        return $this->withRole(RoleEnum::Resident);
    }

    public function additional(): static
    {
        return $this->withRole(RoleEnum::Additional);
    }

    public function external(): static
    {
        return $this->withRole(RoleEnum::External);
    }

    public function staff(): static
    {
        return $this->withRole(RoleEnum::Staff);
    }

    public function billingContact(): static
    {
        return $this->withRole(RoleEnum::BillingContact);
    }
}

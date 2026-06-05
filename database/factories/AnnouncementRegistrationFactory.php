<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementRegistrationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementRegistration>
 */
class AnnouncementRegistrationFactory extends Factory
{
    protected $model = AnnouncementRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory()->event(),
            'user_id' => User::factory(),
            'status' => AnnouncementRegistrationStatus::Registered->value,
            'registered_at' => now(),
        ];
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnnouncementRegistrationStatus::Attended->value,
        ]);
    }
}

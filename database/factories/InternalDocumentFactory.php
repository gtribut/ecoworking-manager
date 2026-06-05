<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Audience;
use App\Enums\InternalDocumentType;
use App\Models\InternalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalDocument>
 */
class InternalDocumentFactory extends Factory
{
    protected $model = InternalDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => InternalDocumentType::Charter->value,
            'title' => fake()->sentence(3),
            'version' => '1.0',
            'body' => fake()->paragraphs(3, true),
            'audience' => Audience::All->value,
            'published_at' => now(),
            'is_active' => true,
        ];
    }

    public function cgu(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InternalDocumentType::Cgu->value,
            'title' => 'Conditions générales d\'utilisation',
        ]);
    }
}

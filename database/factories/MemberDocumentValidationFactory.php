<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InternalDocument;
use App\Models\MemberDocumentValidation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberDocumentValidation>
 *
 * Append-only : la version validée est snapshotée sur la ligne.
 */
class MemberDocumentValidationFactory extends Factory
{
    protected $model = MemberDocumentValidation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_document_id' => InternalDocument::factory(),
            'user_id' => User::factory(),
            'version' => '1.0',
            'validated_at' => now(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}

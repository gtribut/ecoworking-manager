<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdministrativeDocumentType;
use App\Models\AdministrativeDocument;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeDocument>
 */
class AdministrativeDocumentFactory extends Factory
{
    protected $model = AdministrativeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => AdministrativeDocumentType::Contract->value,
            'title' => fake()->sentence(3),
            'pdf_path' => 'documents/'.fake()->uuid().'.pdf',
            'document_date' => now()->toDateString(),
        ];
    }

    public function domiciliation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AdministrativeDocumentType::Domiciliation->value,
            'title' => 'Contrat de domiciliation',
        ]);
    }
}

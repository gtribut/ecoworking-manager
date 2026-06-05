<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 *
 * Brouillon par défaut : pas de numéro (le compteur n'est consommé qu'à
 * l'émission, §6.5). Les totaux sont à 0 — à dériver des lignes par la suite.
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'billable_type' => 'company',
            'billable_id' => $company,
            'status' => InvoiceStatus::Draft->value,
        ];
    }

    /**
     * Facture émise (numéro consommé, montants figés). Le numéro réel passe
     * normalement par InvoiceNumberingService (C6.1) ; ici valeur de test.
     */
    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => 'EW-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'status' => InvoiceStatus::Sent->value,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'billing_name' => fake()->company(),
        ]);
    }

    public function paid(): static
    {
        return $this->issued()->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function creditNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_credit_note' => true,
        ]);
    }
}

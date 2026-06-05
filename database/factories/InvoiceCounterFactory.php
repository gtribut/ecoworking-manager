<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceCounter>
 */
class InvoiceCounterFactory extends Factory
{
    protected $model = InvoiceCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => (int) now()->year,
            'value' => 0,
        ];
    }
}

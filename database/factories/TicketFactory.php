<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Purchase;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            // Détenteur = acheteur du purchase parent par défaut (review 06 M5).
            // La closure reçoit purchase_id déjà résolu ; fallback user dédié
            // si le purchase est retiré par un state (ex. credited()).
            'user_id' => fn (array $attributes) => $attributes['purchase_id'] !== null
                ? Purchase::query()->find($attributes['purchase_id'])?->user_id
                : User::factory(),
            'type' => TicketType::DeskHalfDay->value,
            'status' => TicketStatus::Available->value,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Used->value,
            'consumed_at' => now(),
        ]);
    }

    /** Crédité manuellement par l'admin (sans achat). */
    public function credited(string $reason = 'Geste commercial'): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_id' => null,
            'credited_by' => User::factory()->admin(),
            'credit_reason' => $reason,
        ]);
    }
}

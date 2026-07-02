<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 *
 * Par défaut : abonnement membre (souscripteur = user, facturé sur l'user).
 * Types polymorphes stockés via les alias de la morph map.
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory()->subscription(),
            'subscriber_type' => 'user',
            'subscriber_id' => User::factory(),
            'billable_type' => 'user',
            // Même user que le souscripteur : la closure reçoit les attributs
            // déjà résolus (une même instance de factory passée deux fois
            // serait résolue deux fois → 2 users distincts, review 06 M5).
            'billable_id' => fn (array $attributes) => $attributes['subscriber_id'],
            'status' => SubscriptionStatus::Active->value,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'billing_day' => 1,
        ];
    }

    /**
     * Domiciliation d'une entité : souscripteur = company, facturé sur l'entité.
     * Un seul abonnement « company » actif par entité (index unique §6.3).
     */
    public function domiciliation(?Company $company = null): static
    {
        $company ??= Company::factory()->create();

        return $this->state(fn (array $attributes) => [
            'offer_id' => Offer::factory()->domiciliation(),
            'subscriber_type' => 'company',
            'subscriber_id' => $company->id,
            'billable_type' => 'company',
            'billable_id' => $company->id,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Paused->value,
            'paused_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Ended->value,
            'ends_at' => now()->toDateString(),
        ]);
    }
}

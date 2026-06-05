<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use App\Enums\TicketType;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue des prestations. Les prix ne sont PAS figés chez l'abonné : relus
 * depuis l'offre courante à chaque facturation (§6.7). data_model §4.2.
 */
#[Fillable([
    'code', 'name', 'description', 'type', 'subscriber_kind', 'billing_period',
    'unit_price_ht', 'vat_rate', 'quantity_per_purchase', 'ticket_type',
    'max_per_user', 'requires_active_resident', 'features', 'is_active',
    'is_public', 'display_order',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'subscriber_kind' => SubscriberKind::class,
            'billing_period' => BillingPeriod::class,
            'ticket_type' => TicketType::class,
            'unit_price_ht' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'features' => 'array',
            'requires_active_resident' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @param  Builder<Offer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Offer>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketType;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Achat ponctuel (tickets/packs). Prix SNAPSHOTÉ à l'achat (contrairement aux
 * subscriptions). Crédité par l'admin en MVP. data_model §4.2.
 */
#[Fillable([
    'offer_id', 'user_id', 'billable_type', 'billable_id', 'invoice_id',
    'ticket_type', 'quantity', 'unit_price_ht', 'vat_rate', 'label',
    'purchased_at', 'created_by',
])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_type' => TicketType::class,
            'quantity' => 'integer',
            'unit_price_ht' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

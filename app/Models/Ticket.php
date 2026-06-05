<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ticket unitaire consommable. NON cessible (`user_id` immuable), AUCUNE
 * expiration. data_model §4.2 / §6.9.
 */
#[Fillable([
    'purchase_id', 'user_id', 'type', 'status', 'consumed_at',
    'booking_id', 'desk_occupation_id', 'credited_by', 'credit_reason',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<DeskOccupation, $this> */
    public function deskOccupation(): BelongsTo
    {
        return $this->belongsTo(DeskOccupation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_by');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', TicketStatus::Available->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liaison ligne de facture regroupée ↔ abonnement couvert (C6.5).
 * Traçabilité fine d'une facturation récurrente par entité : pour chaque
 * abonnement consolidé dans une ligne, sa période et sa quote-part HT figée.
 * Porte le backstop d'idempotence UNIQUE (subscription_id, period_start,
 * period_end). data_model §4.4.
 */
#[Fillable([
    'invoice_line_id', 'subscription_id', 'period_start', 'period_end', 'amount_ht',
])]
class InvoiceLineSubscription extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount_ht' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<InvoiceLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

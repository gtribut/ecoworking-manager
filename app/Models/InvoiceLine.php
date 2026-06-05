<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ligne de facture figée à l'émission (jamais recalculée, §6.7). `related`
 * polymorphe : origine de la ligne (subscription/purchase/booking/NULL).
 * data_model §4.4.
 */
#[Fillable([
    'invoice_id', 'related_type', 'related_id', 'description', 'quantity',
    'unit_price_ht', 'discount_rate', 'vat_rate', 'line_total_ht', 'line_vat',
    'line_total_ttc', 'period_start', 'period_end',
])]
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_ht' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_total_ht' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total_ttc' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Encaissement (statuts manuels, pas de paiement en ligne MVP). FK invoices en
 * `restrict` (intégrité comptable). data_model §4.4.
 */
#[Fillable([
    'invoice_id', 'amount', 'paid_at', 'method', 'reference', 'notes', 'created_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return ['invoice_id', 'amount', 'paid_at', 'method', 'reference'];
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
}

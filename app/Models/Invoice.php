<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactRole;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Facture. Émise = JAMAIS supprimée (CGI art. 289) : seuls les `draft` sont
 * supprimables. Annulation = `cancelled` + avoir. Montants figés sur les lignes
 * à l'émission. `number` NULL tant que `draft`. data_model §4.4 / §6.
 */
#[Fillable([
    'number', 'billable_type', 'billable_id', 'status', 'issued_at', 'due_at',
    'billing_name', 'billing_address', 'billing_siret', 'billing_vat_number',
    'subtotal_ht', 'total_vat', 'total_ttc', 'amount_paid', 'pdf_path', 'notes',
    'is_credit_note', 'credit_note_for_invoice_id', 'cancellation_credit_note_id',
    'cancelled_at', 'factur_x_xml_path', 'pa_transmission_id', 'pa_transmission_status',
    'emitted_by',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'billing_address' => 'array',
            'subtotal_ht' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'is_credit_note' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return [
            'status', 'number', 'issued_at', 'due_at', 'subtotal_ht', 'total_vat',
            'total_ttc', 'amount_paid', 'cancelled_at',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Destinataires à notifier pour cette facture (C8) : si le billable est un
     * `User`, lui-même ; si c'est une `Company`, ses contacts facturation
     * rattachés à un compte utilisateur. Miroir du périmètre de visibilité
     * facturation (InvoicePolicy / InvoiceController), mais côté push.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        $billable = $this->billable;

        if ($billable instanceof User) {
            return collect([$billable]);
        }

        if ($billable instanceof Company) {
            return User::query()
                ->whereHas('contacts', function (Builder $q) use ($billable): void {
                    $q->where('company_id', $billable->getKey())
                        ->where('role', ContactRole::Billing->value);
                })
                ->get();
        }

        return collect();
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Facture annulée par cet avoir (self-FK).
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function creditNoteForInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_for_invoice_id');
    }

    /**
     * Avoir d'annulation émis pour cette facture (self-FK).
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function cancellationCreditNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'cancellation_credit_note_id');
    }

    /** @return BelongsTo<User, $this> */
    public function emittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitted_by');
    }

    /**
     * Factures définitivement émises (numéro consommé, donc plus jamais `draft`).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeIssued(Builder $query): void
    {
        $query->whereNotNull('number');
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Draft->value);
    }
}

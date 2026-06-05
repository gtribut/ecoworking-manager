<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entité billable : entreprise (`company`, SIRET) ou particulier (`individual`).
 * Porte la remise négociée et le mandat SEPA. data_model §4.1.
 */
#[Fillable([
    'entity_type', 'status', 'legal_name', 'legal_form', 'siret', 'vat_number',
    'ape_code', 'first_name', 'last_name', 'birth_date', 'billing_email',
    'address_line1', 'address_line2', 'postal_code', 'city', 'country',
    'preferred_payment_method', 'sepa_iban_last4', 'sepa_mandate_reference',
    'sepa_mandate_signed_at', 'sepa_mandate_path', 'discount_rate',
    'discount_scope', 'discount_note', 'admin_notes',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => CompanyType::class,
            'status' => CompanyStatus::class,
            'preferred_payment_method' => PaymentMethod::class,
            'birth_date' => 'date',
            'sepa_mandate_signed_at' => 'date',
            'discount_rate' => 'decimal:2',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return [
            'entity_type', 'status', 'legal_name', 'siret', 'vat_number', 'billing_email',
            'preferred_payment_method', 'discount_rate', 'discount_scope',
        ];
    }

    /** @return HasMany<MemberProfile, $this> */
    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasMany<AdministrativeDocument, $this> */
    public function administrativeDocuments(): HasMany
    {
        return $this->hasMany(AdministrativeDocument::class);
    }

    /** @return MorphMany<Subscription, $this> */
    public function subscriptionsAsSubscriber(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /** @return MorphMany<Subscription, $this> */
    public function subscriptionsAsBillable(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'billable');
    }

    /** @return MorphMany<Invoice, $this> */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    /**
     * @param  Builder<Company>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CompanyStatus::Active->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'calendar_token', 'notify_email', 'notify_in_app', 'theme'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'notify_email' => 'boolean',
            'notify_in_app' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Liste blanche d'audit : jamais de secrets/2FA/token (CLAUDE.md §3.4).
     *
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'theme', 'anonymized_at'];
    }

    /** Nom complet (annuaire, factures). */
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** @return HasOne<MemberProfile, $this> */
    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<DeskOccupation, $this> */
    public function deskOccupations(): HasMany
    {
        return $this->hasMany(DeskOccupation::class);
    }

    /** @return HasMany<DeskAbsence, $this> */
    public function deskAbsences(): HasMany
    {
        return $this->hasMany(DeskAbsence::class);
    }

    /** @return HasMany<AnnouncementRegistration, $this> */
    public function announcementRegistrations(): HasMany
    {
        return $this->hasMany(AnnouncementRegistration::class);
    }

    /** @return HasMany<MemberDocumentValidation, $this> */
    public function documentValidations(): HasMany
    {
        return $this->hasMany(MemberDocumentValidation::class);
    }

    /**
     * Abonnements dont l'utilisateur est le souscripteur (abo membre).
     *
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /**
     * Factures adressées à l'utilisateur (billable particulier).
     *
     * @return MorphMany<Invoice, $this>
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }
}

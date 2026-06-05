<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ContactRole;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
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

    /** Administrateur back-office (court-circuite l'isolation dans les Policies). */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /** Détient le rôle additionnel donnant accès au module facturation (PRD §3.6.1). */
    public function isBillingContact(): bool
    {
        return $this->hasRole(Role::BillingContact->value);
    }

    /**
     * Identifiants des entités juridiques (`companies`) rattachées à l'utilisateur :
     * son entité de membre (`member_profiles.company_id`) et les entités dont il est
     * contact facturation (`contacts.role = billing`). PRD §2.5 / §3.6.
     *
     * C'est le **périmètre** d'entités ; la visibilité facturation y ajoute le rôle
     * `billing_contact` (combiné dans les Policies), mais la simple consultation de
     * l'entité en lecture seule est ouverte à tout membre rattaché (PRD §2.5).
     *
     * @return Collection<int, int>
     */
    public function linkedCompanyIds(): Collection
    {
        return $this->contacts()
            ->where('role', ContactRole::Billing->value)
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->push($this->memberProfile?->company_id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * L'utilisateur a-t-il le périmètre de cette entité billable (`User` en nom
     * propre, ou `Company` rattachée) ? Ne vérifie PAS le rôle `billing_contact` :
     * c'est à la Policy de combiner rôle + périmètre.
     */
    public function canBillFor(Model $billable): bool
    {
        if ($billable instanceof self) {
            return $billable->is($this);
        }

        if ($billable instanceof Company) {
            return $this->linkedCompanyIds()->contains($billable->getKey());
        }

        return false;
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

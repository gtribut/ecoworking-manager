<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ContactRole;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'calendar_token', 'notify_email', 'notify_in_app', 'theme'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
// Invalidation des magic links à tout changement de mot de passe (C12.8a).
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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
            // 2FA Filament (admin) : secret/recovery chiffrés au repos (RGPD §3.4).
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Accès au back-office Filament : admins uniquement (C3.1). Le contrat
     * `FilamentUser` est OBLIGATOIRE en prod — sans lui, tout compte authentifié
     * (membre inclus) accéderait au panel. L'enforcement 2FA est géré par le
     * panel (`multiFactorAuthentication(isRequired: true)`).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /**
     * 2FA Filament natif (TOTP) — distinct du 2FA Fortify (portail membre).
     * Secret stocké chiffré sur `app_authentication_secret`.
     */
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /** Libellé affiché dans l'app d'authentification (Google Authenticator…). */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * Codes de récupération 2FA Filament (chiffrés via cast `encrypted:array`).
     *
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
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

    /** Nom affiché par Filament (menu utilisateur, MFA) — le modèle n'a pas de colonne `name`. */
    public function getFilamentName(): string
    {
        return $this->fullName();
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
     * Token secret des flux iCal d'abonnement (PRD §3.5.8, C9.2). URL de
     * capacité : les clients agenda ne peuvent pas s'authentifier par session,
     * donc le token porte l'autorisation. Révocable (régénération) → invalide
     * immédiatement les anciens abonnements.
     */
    public function regenerateCalendarToken(): string
    {
        $this->calendar_token = bin2hex(random_bytes(24));
        $this->save();

        return $this->calendar_token;
    }

    /** Garantit la présence d'un token (le crée à la volée si absent). */
    public function ensureCalendarToken(): string
    {
        return $this->calendar_token ?? $this->regenerateCalendarToken();
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

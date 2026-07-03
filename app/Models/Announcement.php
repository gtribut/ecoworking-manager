<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementRegistrationStatus;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Observers\AnnouncementObserver;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Annonce (info/event/alert), inscription optionnelle aux events. data_model §4.5.
 */
#[ObservedBy(AnnouncementObserver::class)]
#[Fillable([
    'type', 'title', 'body', 'cover_image_path', 'event_starts_at', 'event_ends_at',
    'location', 'max_participants', 'requires_registration', 'visibility', 'status',
    'published_at', 'created_by',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'visibility' => Audience::class,
            'status' => AnnouncementStatus::class,
            'event_starts_at' => 'datetime',
            'event_ends_at' => 'datetime',
            'requires_registration' => 'boolean',
            'max_participants' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<AnnouncementRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(AnnouncementRegistration::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AnnouncementStatus::Published->value);
    }

    /**
     * Chemin de lecture portail (C12.3, CLAUDE.md §3.1) : publiées uniquement,
     * filtrées par audience. Un admin voit toutes les annonces publiées ; un
     * membre voit `all` + les audiences correspondant à ses rôles
     * (cf. Audience::roles()).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->published();

        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn('visibility', self::audiencesFor($user));
    }

    /** L'annonce est-elle lisible par ce membre sur le portail ? */
    public function isVisibleTo(User $user): bool
    {
        if ($this->status !== AnnouncementStatus::Published) {
            return false;
        }

        return $user->isAdmin() || in_array($this->visibility->value, self::audiencesFor($user), true);
    }

    /** Événement ouvert aux inscriptions (bouton RSVP côté portail). */
    public function acceptsRegistrations(): bool
    {
        return $this->type === AnnouncementType::Event && $this->requires_registration;
    }

    /**
     * Inscriptions comptant pour la jauge (PRD §4.11.3) : les annulées ne
     * consomment pas de place.
     *
     * @return HasMany<AnnouncementRegistration, $this>
     */
    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->whereIn('status', [
            AnnouncementRegistrationStatus::Registered->value,
            AnnouncementRegistrationStatus::Attended->value,
        ]);
    }

    /**
     * Valeurs de visibilité lisibles par ce membre (`all` + audiences de ses rôles).
     *
     * @return list<string>
     */
    private static function audiencesFor(User $user): array
    {
        $audiences = [Audience::All->value];

        foreach ([Audience::Residents, Audience::Additional, Audience::BillingContact] as $audience) {
            if ($user->hasAnyRole($audience->roleValues())) {
                $audiences[] = $audience->value;
            }
        }

        return $audiences;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Audience;
use App\Enums\InternalDocumentType;
use App\Observers\InternalDocumentObserver;
use Database\Factories\InternalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document commun versionné (charte, CGU, droit image). Changement de version
 * → re-validation requise. data_model §4.5.
 */
#[Fillable([
    'type', 'title', 'version', 'body', 'pdf_path', 'audience',
    'published_at', 'is_active', 'created_by',
])]
#[ObservedBy(InternalDocumentObserver::class)]
class InternalDocument extends Model
{
    /** @use HasFactory<InternalDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InternalDocumentType::class,
            'audience' => Audience::class,
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<MemberDocumentValidation, $this> */
    public function validations(): HasMany
    {
        return $this->hasMany(MemberDocumentValidation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<InternalDocument>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Document actif dont la date de publication est atteinte — source unique
     * de la règle « publié », partagée par le chemin de lecture portail et la
     * diffusion des notifications (lot G).
     *
     * @param  Builder<InternalDocument>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->active()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Le document est-il publié ? Évalué CÔTÉ SQL (`exists()`) et non en PHP :
     * une ligne fraîchement écrite est relue décalée du fuseau (piège timezone
     * du dépôt), donc `published_at->isPast()` mentirait à la publication.
     * Une publication PROGRAMMÉE (date future) ne notifie donc pas : elle
     * deviendra visible sans notification (limite connue, cf. rapport lot G).
     */
    public function isPublished(): bool
    {
        return static::query()->whereKey($this->getKey())->published()->exists();
    }

    /**
     * Chemin de lecture portail (C12.4, CLAUDE.md §3.1) : documents actifs,
     * publiés, dont l'audience couvre les rôles du membre. Ce sont eux qui
     * apparaissent dans « Documents à valider » (PRD §3.3.2, §5.3).
     *
     * @param  Builder<InternalDocument>  $query
     */
    public function scopeApplicableTo(Builder $query, User $user): void
    {
        $query->published();

        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn('audience', self::audiencesFor($user));
    }

    /**
     * Le document est-il applicable à ce membre (validable / téléchargeable) ?
     * Délégué au scope `applicableTo` (source unique de la règle) plutôt que
     * recalculé en PHP : la comparaison `published_at <= now()` doit se faire
     * côté SQL, où l'écriture et la lecture des timestamps sont cohérentes.
     */
    public function isApplicableTo(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->applicableTo($user)
            ->exists();
    }

    /**
     * Valeurs d'audience couvrant ce membre (`all` + audiences de ses rôles).
     * Même règle que Announcement::audiencesFor (2e occurrence — à extraire
     * sur l'enum Audience si un 3e usage apparaît).
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

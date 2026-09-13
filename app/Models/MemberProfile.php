<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemberProfileStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\MemberProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil public (annuaire) + rattachement entité + bureau attitré.
 * 1-1 optionnel avec `users`. data_model §4.1.
 */
#[Fillable([
    'user_id', 'company_id', 'desk_id', 'status', 'arrival_date', 'departure_date',
    'photo_path', 'birth_date', 'job_title', 'bio', 'interests', 'linkedin_url',
    'website_url', 'show_in_directory', 'newsletter_opt_in', 'admin_notes',
])]
class MemberProfile extends Model
{
    /** @use HasFactory<MemberProfileFactory> */
    use Auditable, HasFactory;

    /**
     * Défauts EXPLICITES pour les booléens audités : sans eux, une création
     * sans valeur laisse l'attribut absent du modèle, et la première écriture
     * est journalisée `null → false` (faux positif documenté du trait Auditable).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_in_directory' => false,
        'newsletter_opt_in' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MemberProfileStatus::class,
            'arrival_date' => 'date',
            'departure_date' => 'date',
            'birth_date' => 'date',
            'show_in_directory' => 'boolean',
            'newsletter_opt_in' => 'boolean',
        ];
    }

    /**
     * Champs sensibles tracés (PRD §3.4.5 : opt-in newsletter, visibilité
     * annuaire) + rattachements structurants (bureau attitré, entité). Jamais
     * la bio, la photo ni les notes admin (contenu libre, sans enjeu d'audit).
     *
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return ['show_in_directory', 'newsletter_opt_in', 'desk_id', 'company_id'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<resource, $this> */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'desk_id');
    }

    /**
     * @param  Builder<MemberProfile>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberProfileStatus::Active->value);
    }

    /**
     * Profils visibles dans l'annuaire (opt-in explicite).
     *
     * @param  Builder<MemberProfile>  $query
     */
    public function scopeInDirectory(Builder $query): void
    {
        $query->where('show_in_directory', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemberProfileStatus;
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
    use HasFactory;

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

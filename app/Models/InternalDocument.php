<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Audience;
use App\Enums\InternalDocumentType;
use Database\Factories\InternalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}

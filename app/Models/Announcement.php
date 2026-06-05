<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Annonce (info/event/alert), inscription optionnelle aux events. data_model §4.5.
 */
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
}

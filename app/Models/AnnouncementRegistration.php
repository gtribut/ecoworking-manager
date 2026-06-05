<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementRegistrationStatus;
use Database\Factories\AnnouncementRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription d'un membre à un event. Une inscription unique par
 * (annonce, user). data_model §4.5.
 */
#[Fillable(['announcement_id', 'user_id', 'status', 'registered_at'])]
class AnnouncementRegistration extends Model
{
    /** @use HasFactory<AnnouncementRegistrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnnouncementRegistrationStatus::class,
            'registered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

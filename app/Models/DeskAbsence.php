<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use Database\Factories\DeskAbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Déclaration d'absence d'un résident/staff sur SON bureau. Expansion des
 * récurrences à la lecture (jamais de pré-génération). data_model §4.3.
 */
#[Fillable([
    'desk_id', 'user_id', 'date_start', 'date_end', 'period',
    'recurrence_type', 'recurrence_day_of_week', 'notes', 'created_by',
])]
class DeskAbsence extends Model
{
    /** @use HasFactory<DeskAbsenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'period' => Period::class,
            'recurrence_type' => DeskAbsenceRecurrence::class,
            'recurrence_day_of_week' => 'integer',
        ];
    }

    /** @return BelongsTo<resource, $this> */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'desk_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

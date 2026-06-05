<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use Database\Factories\DeskOccupationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupation effective d'un bureau, par demi-journée. Stocke surtout les
 * `external_ticket` ; la présence des résidents est dérivée. data_model §4.3.
 */
#[Fillable([
    'desk_id', 'user_id', 'date', 'period', 'source', 'ticket_id', 'status', 'created_by',
])]
class DeskOccupation extends Model
{
    /** @use HasFactory<DeskOccupationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => Period::class,
            'source' => DeskOccupationSource::class,
            'status' => DeskOccupationStatus::class,
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

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

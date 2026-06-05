<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique APPEND-ONLY des consentements RGPD (newsletter, annuaire, droit
 * image, CGU…). Une ligne par changement, jamais d'écrasement. data_model §4.1.
 */
#[Fillable([
    'user_id', 'type', 'granted', 'granted_at', 'revoked_at', 'ip_address', 'source',
])]
class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Auth\MagicLinkService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton de connexion par magic link (C12.8a, PRD §3.2 / ADR-0011).
 *
 * `token_hash` = SHA-256 du jeton envoyé par email — le jeton en clair n'est
 * JAMAIS persisté. Usage unique (`used_at`) + expiration (`expires_at`).
 * Cycle de vie géré exclusivement par {@see MagicLinkService}.
 */
#[Fillable(['user_id', 'token_hash', 'expires_at'])]
class MagicLinkToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        // SoftDeletes sur User : un compte supprimé/anonymisé résout à null.
        return $this->belongsTo(User::class);
    }
}

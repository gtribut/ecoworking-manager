<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemberDocumentValidationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND-ONLY : une ligne par validation (qui/quand/version). Nouvelle version
 * → nouvelle ligne, jamais d'écrasement (§6.11). data_model §4.5.
 */
#[Fillable([
    'internal_document_id', 'user_id', 'version', 'validated_at', 'ip_address',
])]
class MemberDocumentValidation extends Model
{
    /** @use HasFactory<MemberDocumentValidationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InternalDocument, $this> */
    public function internalDocument(): BelongsTo
    {
        return $this->belongsTo(InternalDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdministrativeDocumentType;
use Database\Factories\AdministrativeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document propre à une entité (contrats, avenants, domiciliation). Rétention
 * 10 ans. data_model §4.5.
 */
#[Fillable([
    'company_id', 'type', 'title', 'pdf_path', 'document_date', 'uploaded_by',
])]
class AdministrativeDocument extends Model
{
    /** @use HasFactory<AdministrativeDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdministrativeDocumentType::class,
            'document_date' => 'date',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

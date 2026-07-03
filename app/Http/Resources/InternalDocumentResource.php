<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InternalDocument;
use App\Models\MemberDocumentValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document interne exposé au portail (PRD §3.3.2, §5.3 — C12.4). Le statut de
 * validation est calculé pour la VERSION COURANTE : une validation d'une
 * version antérieure ne compte pas (re-validation requise à chaque nouvelle
 * version). La relation `validations` doit être eager-loadée restreinte à
 * l'utilisateur courant par le controller.
 *
 * @mixin InternalDocument
 */
final class InternalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MemberDocumentValidation|null $validation */
        $validation = $this->validations->firstWhere('version', $this->version);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'version' => $this->version,
            'body' => $this->body,
            'published_at' => $this->published_at?->toIso8601String(),
            'pdf_available' => $this->pdf_path !== null,
            'is_validated' => $validation !== null,
            'validated_at' => $validation?->validated_at->toIso8601String(),
        ];
    }
}

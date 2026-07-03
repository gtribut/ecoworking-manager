<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AdministrativeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document administratif d'entité exposé au portail (PRD §3.6.3 — C12.4).
 * Projection minimale : le contenu reste servi en streaming via l'endpoint de
 * téléchargement (disque privé, jamais d'URL publique). `company_name` permet
 * au billing_contact multi-entités de distinguer les documents.
 *
 * @mixin AdministrativeDocument
 */
final class AdministrativeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'document_date' => $this->document_date?->toDateString(),
            'company_name' => $this->company?->name,
            'pdf_available' => $this->pdf_path !== null,
        ];
    }
}

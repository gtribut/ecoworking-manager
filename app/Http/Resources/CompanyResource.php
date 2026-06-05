<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entité juridique rattachée, exposée en **lecture seule** au portail membre
 * (PRD §3.4.3 « Mon entreprise »). Aucune donnée sensible : pas d'IBAN/mandat
 * SEPA, pas de notes admin, pas de remise négociée.
 *
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'legal_form' => $this->legal_form,
            'siret' => $this->siret,
            'vat_number' => $this->vat_number,
            'billing_email' => $this->billing_email,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
        ];
    }
}

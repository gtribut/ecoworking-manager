<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Entité juridique rattachée, exposée en **lecture seule** au portail membre
 * (PRD §3.4.3 « Mon entreprise » / §3.6.4 module administratif). Jamais de
 * donnée sensible : pas d'IBAN complet ni de mandat SEPA, pas de notes admin,
 * pas de remise négociée.
 *
 * Les données de facturation (mode de paiement préféré, 4 derniers chiffres de
 * l'IBAN) ne sont ajoutées que pour un viewer **contact de facturation de cette
 * entité** (`CompanyPolicy::viewBillingDetails`) : un simple résident rattaché
 * ne les voit pas (les clés sont absentes, pas nulles).
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
            ...$this->billingDetails($request),
        ];
    }

    /**
     * Données de facturation réservées au contact de facturation de l'entité
     * (PRD §3.6.4). `iban_last4` est un rappel — l'IBAN complet n'est jamais
     * stocké en clair (CLAUDE.md §3.4), le mandat SEPA n'est jamais exposé.
     *
     * @return array<string, mixed>
     */
    private function billingDetails(Request $request): array
    {
        if (! Gate::forUser($request->user())->allows('viewBillingDetails', $this->resource)) {
            return [];
        }

        return [
            'payment_method' => $this->preferred_payment_method?->value,
            'payment_method_label' => $this->preferred_payment_method?->getLabel(),
            'iban_last4' => $this->sepa_iban_last4,
        ];
    }
}

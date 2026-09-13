<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Bloc entité du module administratif (PRD §3.6.4) : « Mon entreprise » pour
 * une entreprise, « Mes données de facturation » pour un particulier.
 *
 * Périmètre = `linkedCompanyIds()`, comme les factures et les documents — et
 * non `memberProfile->company` : un `billing_contact` pur n'a pas de profil
 * membre, et un contact peut couvrir plusieurs entités. La facturation « en
 * nom propre » n'est pas un cas particulier : le particulier est le
 * `billing_contact` d'une entité `individual` (PRD §3.3.2, acté 2026-09-13).
 *
 * Endpoint séparé de `GET /api/profile` à dessein : le profil est auto-scopé
 * sur l'entité du profil membre (lecture ouverte à tout membre rattaché),
 * là où ce bloc est une **liste** gouvernée par le rôle facturation.
 */
final class BillingEntityController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Hors rôle billing : 403 explicite, comme les factures (PRD §3.6.1).
        Gate::authorize('viewAnyBillingDetails', Company::class);

        // Périmètre : les entités dont il est explicitement contact facturation
        // (`contacts.role = billing`) — être rattaché comme résident ne suffit
        // pas pour des coordonnées bancaires. CompanyResource reste l'unique
        // autorité sur les champs rendus (CompanyPolicy::viewBillingDetails).
        $companies = Company::query()
            ->whereIn('id', $user->billingContactCompanyIds())
            ->orderBy('legal_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();

        return CompanyResource::collection($companies);
    }
}

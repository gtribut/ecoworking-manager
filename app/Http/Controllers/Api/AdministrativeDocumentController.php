<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdministrativeDocumentResource;
use App\Models\AdministrativeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents administratifs d'entité côté portail (PRD §3.6.3 — C12.4).
 * Lecture seule, même périmètre que les factures (InvoiceController) : réservé
 * au rôle `billing_contact` sur ses entités rattachées. Le PDF est servi en
 * streaming depuis le disque privé (AdministrativeDocumentPolicy::download).
 */
final class AdministrativeDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = AdministrativeDocument::query()
            ->with('company')
            ->orderByDesc('document_date')
            ->orderByDesc('id');

        $this->scopeToBillingPerimeter($query, $user);

        return AdministrativeDocumentResource::collection($query->paginate(20));
    }

    public function downloadPdf(Request $request, AdministrativeDocument $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        abort_if(
            $document->pdf_path === null || ! Storage::exists($document->pdf_path),
            404,
            'PDF non disponible pour ce document.',
        );

        $filename = Str::slug($document->title).'.pdf';

        return Storage::download($document->pdf_path, $filename);
    }

    /**
     * Restreint la requête au périmètre du membre, en miroir de la Policy
     * (et d'InvoiceController) : entités rattachées dont il est contact
     * facturation. Sans le rôle `billing_contact`, aucun accès.
     *
     * @param  Builder<AdministrativeDocument>  $query
     */
    private function scopeToBillingPerimeter(Builder $query, User $user): void
    {
        if (! $user->isBillingContact()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('company_id', $user->linkedCompanyIds());
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexInvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Factures côté portail membre (PRD §3.6.2). Lecture seule : un membre ne voit
 * que les factures de son périmètre de facturation, et uniquement émises (les
 * brouillons restent internes). La visibilité reflète l'InvoicePolicy (C2.4) :
 * réservée au rôle `billing_contact` sur les entités rattachées / en nom propre.
 */
final class InvoiceController extends Controller
{
    /**
     * Liste paginée, triable et filtrable (PRD §3.6.2). L'autorisation (rôle
     * `billing_contact`, PRD §2.5/§3.6.1) est portée par la Form Request : un
     * membre sans ce rôle reçoit un refus explicite, pas une liste vide.
     */
    public function index(IndexInvoicesRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Invoice::query()
            ->whereNotNull('number'); // jamais de brouillon côté membre

        // Périmètre d'abord : tout ce qui suit ne peut que le restreindre.
        $this->scopeToBillingPerimeter($query, $user);
        $this->applyFilters($query, $request);

        $query->orderBy($request->sort(), $request->direction())
            ->orderBy('id', $request->direction()); // départage stable

        return InvoiceResource::collection(
            $query->paginate($request->perPage())->withQueryString()
        );
    }

    public function downloadPdf(Request $request, Invoice $invoice): StreamedResponse
    {
        Gate::authorize('download', $invoice);

        abort_if(
            $invoice->pdf_path === null || ! Storage::exists($invoice->pdf_path),
            404,
            'PDF non disponible pour cette facture.',
        );

        $filename = ($invoice->number ?? "facture-{$invoice->id}").'.pdf';

        return Storage::download($invoice->pdf_path, $filename);
    }

    /**
     * Applique tri/filtres/recherche validés. `issued_at` est une colonne DATE
     * (pas un timestamptz) : les filtres mois/année se comparent donc côté SQL
     * (`whereYear`/`whereMonth`), sans Carbon PHP — aucun décalage de fuseau
     * possible (piège connu du projet sur les timestamps).
     *
     * @param  Builder<Invoice>  $query
     */
    private function applyFilters($query, IndexInvoicesRequest $request): void
    {
        $query->when($request->year(), fn ($q, int $year) => $q->whereYear('issued_at', $year))
            ->when($request->month(), fn ($q, int $month) => $q->whereMonth('issued_at', $month))
            ->when($request->status(), fn ($q, string $status) => $q->where('status', $status))
            ->when(
                $request->numberSearch(),
                // Recherche insensible à la casse sur le numéro seul ; jokers
                // déjà échappés par la Form Request (pas de DB::raw, §7).
                fn ($q, string $search) => $q->where('number', 'ilike', '%'.$search.'%'),
            );
    }

    /**
     * Restreint la requête au périmètre de facturation du membre, en miroir de
     * l'InvoicePolicy : entités rattachées dont il est contact facturation +
     * factures à son nom propre. Sans le rôle `billing_contact`, aucun accès.
     *
     * @param  Builder<Invoice>  $query
     */
    private function scopeToBillingPerimeter($query, User $user): void
    {
        if (! $user->isBillingContact()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $companyIds = $user->linkedCompanyIds();

        $query->where(function ($q) use ($companyIds, $user): void {
            $q->where(function ($q2) use ($companyIds): void {
                $q2->where('billable_type', 'company')->whereIn('billable_id', $companyIds);
            })->orWhere(function ($q2) use ($user): void {
                $q2->where('billable_type', 'user')->where('billable_id', $user->id);
            });
        });
    }
}

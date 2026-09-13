<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Module masqué de la nav sans rôle billing (PRD §2.5/§3.6.1) : un
        // membre sans ce rôle reçoit un refus explicite, pas une liste vide.
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->whereNotNull('number') // jamais de brouillon côté membre
            ->latest('issued_at')
            ->latest('id');

        $this->scopeToBillingPerimeter($query, $user);

        return InvoiceResource::collection($query->paginate(20));
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

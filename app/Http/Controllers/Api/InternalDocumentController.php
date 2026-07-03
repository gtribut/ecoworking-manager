<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InternalDocumentResource;
use App\Models\InternalDocument;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents internes côté portail (PRD §3.3.2, §5.3 — C12.4). Liste les
 * documents actifs et publiés dont l'audience couvre le membre, avec son
 * statut de validation (version courante). Le PDF est servi en streaming
 * depuis le disque privé — jamais d'URL publique (CLAUDE.md §3.1).
 */
final class InternalDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $documents = InternalDocument::query()
            ->applicableTo($user)
            // Uniquement MES validations : le statut affiché est celui du
            // membre courant, jamais celui d'un autre (CLAUDE.md §3.1).
            ->with(['validations' => fn (HasMany $query) => $query->where('user_id', $user->id)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return InternalDocumentResource::collection($documents);
    }

    public function downloadPdf(Request $request, InternalDocument $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        abort_if(
            $document->pdf_path === null || ! Storage::exists($document->pdf_path),
            404,
            'PDF non disponible pour ce document.',
        );

        $filename = Str::slug("{$document->title} v{$document->version}").'.pdf';

        return Storage::download($document->pdf_path, $filename);
    }
}

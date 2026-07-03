<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalDocument;
use App\Models\MemberDocumentValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Validation d'un document interne par le membre (PRD §5.3 — C12.4).
 *
 * Auto-scopé par construction : la route ne porte que le document — la
 * validation créée est TOUJOURS celle de l'utilisateur authentifié, avec la
 * version courante snapshotée côté serveur (aucune donnée client, CLAUDE.md
 * §3.1/§3.2). Append-only : une nouvelle version du document redéclenche une
 * validation, sans jamais écraser l'historique (§6.11).
 */
final class InternalDocumentValidationController extends Controller
{
    public function __invoke(Request $request, InternalDocument $document): JsonResponse
    {
        $user = $request->user();

        // Policy exercée : permission validate-internal-document (un
        // billing_contact pur ne l'a pas — PermissionSeeder).
        Gate::authorize('create', MemberDocumentValidation::class);

        // Un document inactif, non publié ou hors audience n'est pas validable.
        abort_unless($document->isApplicableTo($user), 403);

        $alreadyValidated = $document->validations()
            ->where('user_id', $user->id)
            ->where('version', $document->version)
            ->exists();

        abort_if($alreadyValidated, 422, 'Document déjà validé dans sa version courante.');

        $validation = $document->validations()->create([
            'user_id' => $user->id,
            'version' => $document->version, // snapshot de la version validée
            'validated_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Document validé.',
            'validated_at' => $validation->validated_at->toIso8601String(),
        ], 201);
    }
}

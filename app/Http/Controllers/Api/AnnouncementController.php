<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Actualités & événements du portail (PRD §3.3.2, C12.3). Lecture seule,
 * toujours via le scope `visibleTo` : publiées uniquement, filtrées par
 * l'audience de l'utilisateur (CLAUDE.md §3.1) — une annonce hors audience
 * ou non publiée répond 404, jamais son contenu.
 */
final class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->with(['registrations' => fn (HasMany $query) => $query->where('user_id', $user->id)])
            ->withCount('activeRegistrations')
            ->orderByDesc('published_at')
            ->paginate(10);

        return AnnouncementResource::collection($announcements);
    }

    public function show(Request $request, int $announcement): AnnouncementResource
    {
        $user = $request->user();

        // Récupération À TRAVERS le scope : une annonce brouillon, archivée ou
        // hors audience est indistinguable d'une annonce inexistante (404).
        $found = Announcement::query()
            ->visibleTo($user)
            ->with(['registrations' => fn (HasMany $query) => $query->where('user_id', $user->id)])
            ->withCount('activeRegistrations')
            ->findOrFail($announcement);

        return new AnnouncementResource($found);
    }
}

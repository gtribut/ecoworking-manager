<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Services\AnnouncementRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Inscription / désinscription à un événement (PRD §2.5, C12.3).
 *
 * Auto-scopé par construction : la route ne porte que l'annonce — l'inscription
 * manipulée est TOUJOURS celle de l'utilisateur authentifié, jamais un id
 * d'inscription fourni par le client (CLAUDE.md §3.1). La Policy
 * AnnouncementRegistrationPolicy est exercée sur chaque action :
 * `create` (permission register-event — un billing_contact pur ne l'a pas),
 * `delete` (propriété de l'inscription).
 */
final class AnnouncementRegistrationController extends Controller
{
    public function store(
        Request $request,
        int $announcement,
        AnnouncementRegistrationService $registrations,
    ): JsonResponse {
        $user = $request->user();

        // Même règle de lecture que le détail : hors audience / non publiée → 404.
        $found = Announcement::query()->visibleTo($user)->findOrFail($announcement);

        Gate::authorize('create', AnnouncementRegistration::class);

        $registrations->register($user, $found);

        return response()->json(['message' => 'Inscription confirmée.'], 201);
    }

    public function destroy(
        Request $request,
        int $announcement,
        AnnouncementRegistrationService $registrations,
    ): JsonResponse {
        $user = $request->user();

        $found = Announcement::query()->visibleTo($user)->findOrFail($announcement);

        $registration = $found->activeRegistrations()
            ->where('user_id', $user->id)
            ->firstOrFail();

        Gate::authorize('delete', $registration);

        $registrations->cancel($registration);

        return response()->json(['message' => 'Inscription annulée.']);
    }
}

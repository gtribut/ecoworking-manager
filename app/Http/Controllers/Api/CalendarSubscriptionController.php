<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion par le membre de son abonnement iCal (PRD §3.5.8, C9.2). Auto-scopé
 * sur l'utilisateur courant : il consulte / régénère / révoque uniquement son
 * propre token. Le token n'est exposé qu'à travers ses URLs de flux.
 */
final class CalendarSubscriptionController extends Controller
{
    /** URLs de flux courantes (token créé à la volée s'il n'existe pas encore). */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->ensureCalendarToken();

        return response()->json($this->payload($user));
    }

    /** Régénère le token → révoque immédiatement les anciens abonnements. */
    public function regenerate(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->regenerateCalendarToken();

        return response()->json($this->payload($user));
    }

    /** Désactive l'abonnement (les flux renvoient alors 404). */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->calendar_token = null;
        $user->save();

        // `urls` explicite (même forme que show/regenerate — contrat TS de la SPA).
        return response()->json(['enabled' => false, 'urls' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $token = $user->calendar_token;

        return [
            'enabled' => $token !== null,
            'urls' => $token === null ? null : [
                'mine' => route('calendar.mine', ['token' => $token]),
                'entity' => route('calendar.entity', ['token' => $token]),
            ],
        ];
    }
}

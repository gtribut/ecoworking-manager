<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Renvoie l'utilisateur authentifié (session Sanctum) avec ses rôles et
 * permissions — consommé par la SPA au boot pour masquer les sections
 * inaccessibles (PRD §2.7). Aucune donnée sensible (pas de calendar_token,
 * secrets 2FA, etc.) : projection explicite, pas le modèle brut.
 */
final class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'theme' => $user->theme,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            // Lot B (PRD §2.5) : le module présence/absences est réservé au
            // membre doté d'un bureau attitré — la SPA ne peut pas le déduire
            // des permissions (un `additional` a les mêmes, sans bureau).
            'has_desk' => $user->hasAssignedDesk(),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\DeskOccupation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tickets nomades du membre (PRD §3.5.6) : soldes par type + liste. Auto-scopé
 * sur l'utilisateur authentifié (un membre ne voit que ses tickets).
 */
final class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Les tickets n'existent que pour l'external (ou l'admin) : même garde
        // que la réservation de bureau, pas de policy dédiée redondante
        // (review lot E pt.3 — l'endpoint n'était gardé que par `auth:sanctum`,
        // accessible sans data leak mais incohérent avec le reste du module).
        Gate::authorize('viewAny', DeskOccupation::class);

        $user = $request->user();

        // Soldes des deux types en UNE requête GROUP BY (au lieu d'un COUNT
        // par type). Le format JSON reste inchangé (consommé par la SPA).
        $balances = $user->tickets()
            ->where('status', TicketStatus::Available->value)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return response()->json([
            'balances' => [
                TicketType::DeskHalfDay->value => (int) ($balances[TicketType::DeskHalfDay->value] ?? 0),
                TicketType::MeetingRoomHalfDay->value => (int) ($balances[TicketType::MeetingRoomHalfDay->value] ?? 0),
            ],
            // Eager loading (CLAUDE.md §4.1) : évite le N+1 des accesseurs
            // `purchase`/`booking.resource`/`deskOccupation.desk` de TicketResource.
            'tickets' => TicketResource::collection(
                $user->tickets()
                    ->with(['purchase', 'booking.resource', 'deskOccupation.desk'])
                    ->orderByDesc('id')
                    ->get()
            )->resolve(),
        ]);
    }
}

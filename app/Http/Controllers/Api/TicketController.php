<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tickets nomades du membre (PRD §3.5.6) : soldes par type + liste. Auto-scopé
 * sur l'utilisateur authentifié (un membre ne voit que ses tickets).
 */
final class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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
            'tickets' => TicketResource::collection(
                $user->tickets()->orderByDesc('id')->get()
            )->resolve(),
        ]);
    }
}

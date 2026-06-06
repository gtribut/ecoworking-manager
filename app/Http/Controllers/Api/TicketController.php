<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tickets nomades du membre (PRD §3.5.6) : soldes par type + liste. Auto-scopé
 * sur l'utilisateur authentifié (un membre ne voit que ses tickets).
 */
final class TicketController extends Controller
{
    public function index(Request $request, TicketService $tickets): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balances' => [
                TicketType::DeskHalfDay->value => $tickets->availableCount($user, TicketType::DeskHalfDay),
                TicketType::MeetingRoomHalfDay->value => $tickets->availableCount($user, TicketType::MeetingRoomHalfDay),
            ],
            'tickets' => TicketResource::collection(
                $user->tickets()->orderByDesc('id')->get()
            )->resolve(),
        ]);
    }
}

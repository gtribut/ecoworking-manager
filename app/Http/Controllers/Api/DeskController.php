<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Period;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDeskOccupationRequest;
use App\Http\Resources\DeskOccupationResource;
use App\Http\Resources\ResourceResource;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Services\DeskAvailabilityService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Bureaux nomades pour les externals (PRD §3.5.9) : disponibilité, réservation
 * (occupation + ticket), annulation. Réservation auto-scopée au membre.
 */
final class DeskController extends Controller
{
    public function availability(Request $request, DeskAvailabilityService $desks): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'period' => ['required', 'string', 'in:morning,afternoon,full_day'],
        ]);

        $date = CarbonImmutable::parse($request->string('date')->toString());
        $period = Period::from($request->string('period')->toString());
        $available = $desks->availableDesks($date, $period);

        return response()->json([
            'date' => $date->toDateString(),
            'period' => $period->value,
            'count' => $available->count(),
            'desks' => ResourceResource::collection($available)->resolve(),
        ]);
    }

    public function store(StoreDeskOccupationRequest $request, DeskAvailabilityService $desks, TicketService $tickets): JsonResponse
    {
        $user = $request->user();
        $desk = Resource::findOrFail($request->integer('desk_id'));
        $date = CarbonImmutable::parse($request->string('date')->toString());
        $period = Period::from($request->string('period')->toString());

        $occupation = DB::transaction(function () use ($user, $desk, $date, $period, $desks, $tickets): DeskOccupation {
            $ticket = $tickets->lockFirstAvailable($user, TicketType::DeskHalfDay);

            return $desks->bookForExternal($user, $desk, $date, $period, $ticket, $user->id);
        });

        return (new DeskOccupationResource($occupation->load('desk')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(DeskOccupation $deskOccupation, DeskAvailabilityService $desks): JsonResponse
    {
        Gate::authorize('delete', $deskOccupation);

        $desks->cancelExternal($deskOccupation);

        return response()->json(['message' => 'Réservation de bureau annulée.']);
    }
}

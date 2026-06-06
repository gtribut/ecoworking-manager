<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceResource;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\RoomAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Salles réservables côté portail (PRD §3.5.5). Lecture seule : catalogue des
 * salles de réunion actives + disponibilité d'une salle à une date donnée.
 */
final class RoomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rooms = Resource::query()
            ->where('type', ResourceType::MeetingRoom->value)
            ->where('is_active', true)
            ->where('is_out_of_service', false)
            ->orderBy('display_order')
            ->get();

        return ResourceResource::collection($rooms);
    }

    /**
     * Disponibilité d'une salle pour une journée : créneaux confirmés (busy) +
     * demi-journées réservables si l'utilisateur réserve en external.
     */
    public function availability(Request $request, Resource $room, RoomAvailabilityService $availability): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);
        $date = CarbonImmutable::parse($request->string('date')->toString());

        $busy = Booking::query()
            ->where('resource_id', $room->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('starts_at', $date->toDateString())
            ->orderBy('starts_at')
            ->get(['id', 'starts_at', 'ends_at'])
            ->map(fn (Booking $b): array => [
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
            ]);

        $isExternal = $request->user()?->can(Permission::CreatePaidBooking->value)
            && ! $request->user()?->can(Permission::CreateOwnBooking->value);

        $externalSlots = $isExternal
            ? array_map(fn (array $slot): array => [
                'period' => $slot['period']->value,
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
            ], $availability->externalSlotsFor($room, $date))
            : [];

        return response()->json([
            'date' => $date->toDateString(),
            'busy' => $busy,
            'external_slots' => $externalSlots,
            'is_external' => (bool) $isExternal,
        ]);
    }
}

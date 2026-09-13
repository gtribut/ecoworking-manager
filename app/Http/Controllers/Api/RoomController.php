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
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeCalendar($request);

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
        $this->authorizeCalendar($request);

        // Moindre exposition (review sécu I1) : cet endpoint ne répond que pour
        // les salles de réunion — un bureau (ou la salle event) → 404.
        abort_unless($room->type === ResourceType::MeetingRoom, 404);

        $request->validate(['date' => ['required', 'date']]);
        $date = CarbonImmutable::parse($request->string('date')->toString());

        // Chevauchement réel avec la journée [J 00:00, J+1 00:00) — un simple
        // whereDate(starts_at) raterait une résa à cheval sur minuit.
        $dayStart = $date->startOfDay();
        $dayEnd = $dayStart->addDay();

        $busy = Booking::query()
            ->where('resource_id', $room->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
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

    /**
     * Calendrier des salles : interdit au contact facturation pur (PRD §2.5),
     * qui ne détient pas `view-bookings-calendar`.
     */
    private function authorizeCalendar(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($user->isAdmin() || $user->can(Permission::ViewBookingsCalendar->value)),
            403,
            'Calendrier des salles réservé aux membres.',
        );
    }
}

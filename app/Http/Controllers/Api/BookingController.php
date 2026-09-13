<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\Period;
use App\Enums\ResourceType;
use App\Enums\TicketType;
use App\Exceptions\DomainActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RoomAvailabilityService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Réservations de salle du membre (PRD §3.5.5). Toujours auto-scopé sur
 * l'utilisateur authentifié : un membre ne voit/agit que sur les siennes
 * (CLAUDE.md §3.1). L'anti-double-booking et la consommation de ticket sont
 * délégués aux Services (C7).
 */
final class BookingController extends Controller
{
    /**
     * Liste des réservations du membre. Par défaut : historique complet, plus
     * récentes d'abord. `?upcoming=1` (dashboard PRD §3.3.2 « Mes prochaines
     * réservations ») : uniquement les résas confirmées non terminées, en ordre
     * chronologique — comparaison côté SQL (piège timezone : jamais `isFuture()`
     * PHP sur des lignes fraîches). `?per_page` borné à 50.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // `view-own-bookings` (PRD §2.5) : un contact facturation pur n'a pas
        // de réservations et n'accède pas au module.
        Gate::authorize('viewAny', Booking::class);

        $upcoming = $request->boolean('upcoming');
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with('resource')
            ->when($upcoming, fn ($query) => $query
                ->where('status', BookingStatus::Confirmed->value)
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at'))
            ->unless($upcoming, fn ($query) => $query->orderByDesc('starts_at'))
            ->paginate($perPage);

        return BookingResource::collection($bookings);
    }

    public function store(
        StoreBookingRequest $request,
        BookingService $bookings,
        RoomAvailabilityService $availability,
        TicketService $tickets,
    ): JsonResponse {
        $user = $request->user();
        $room = Resource::findOrFail($request->integer('resource_id'));

        if ($room->type !== ResourceType::MeetingRoom || ! $room->is_active || $room->is_out_of_service) {
            throw new DomainActionException("Cette salle n'est pas réservable.");
        }

        $booking = $request->isExternalBooker()
            ? $this->storeExternal($request, $user, $room, $bookings, $availability, $tickets)
            : $this->storeResident($request, $user, $room, $bookings);

        return (new BookingResource($booking->load('resource')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Booking $booking, BookingService $bookings): JsonResponse
    {
        Gate::authorize('delete', $booking);

        $bookings->cancel($booking, $request->string('reason')->toString() ?: null);

        return response()->json(['message' => 'Réservation annulée.']);
    }

    private function storeResident(StoreBookingRequest $request, User $user, Resource $room, BookingService $bookings): Booking
    {
        return $bookings->create([
            'resource' => $room,
            'user' => $user,
            'starts_at' => CarbonImmutable::parse($request->string('starts_at')->toString()),
            'ends_at' => CarbonImmutable::parse($request->string('ends_at')->toString()),
            'title' => $request->string('title')->toString() ?: null,
            'created_by' => $user->id,
        ]);
    }

    private function storeExternal(
        StoreBookingRequest $request,
        User $user,
        Resource $room,
        BookingService $bookings,
        RoomAvailabilityService $availability,
        TicketService $tickets,
    ): Booking {
        $date = CarbonImmutable::parse($request->string('date')->toString());
        $period = Period::from($request->string('period')->toString());

        if (! $availability->isExternalSlotBookable($room, $date, $period)) {
            throw new DomainActionException('Ce créneau n\'est pas réservable (jour non ouvré ou déjà pris).');
        }

        $bounds = $availability->halfDayBounds($date, $period);

        // Lock + consommation du ticket et création dans la MÊME transaction.
        return DB::transaction(function () use ($user, $room, $bounds, $request, $bookings, $tickets): Booking {
            $ticket = $tickets->lockFirstAvailable($user, TicketType::MeetingRoomHalfDay);

            return $bookings->create([
                'resource' => $room,
                'user' => $user,
                'starts_at' => $bounds['starts_at'],
                'ends_at' => $bounds['ends_at'],
                'title' => $request->string('title')->toString() ?: null,
                'ticket' => $ticket,
                'created_by' => $user->id,
            ]);
        });
    }
}

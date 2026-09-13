<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\Period;
use App\Enums\TicketType;
use App\Exceptions\DomainActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Requests\Api\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\RoomResource;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Ticket;
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
     * récentes d'abord. `?upcoming=1` (dashboard PRD §3.3.2 et vue « Mes
     * prochaines réservations » §3.5.7) : résas confirmées non terminées, en
     * ordre chronologique. `?past=1` (onglet « Historique » §3.5.7) : résas
     * terminées, plus récentes d'abord. Comparaisons côté SQL (piège timezone :
     * jamais `isFuture()` PHP sur des lignes fraîches). `?per_page` borné à 50.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // `view-own-bookings` (PRD §2.5) : un contact facturation pur n'a pas
        // de réservations et n'accède pas au module.
        Gate::authorize('viewAny', Booking::class);

        $upcoming = $request->boolean('upcoming');
        $past = ! $upcoming && $request->boolean('past');
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with(['resource', 'ticket'])
            ->when($upcoming, fn ($query) => $query
                ->where('status', BookingStatus::Confirmed->value)
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at'))
            ->when($past, fn ($query) => $query
                ->where('ends_at', '<', now())
                ->orderByDesc('starts_at'))
            ->unless($upcoming || $past, fn ($query) => $query->orderByDesc('starts_at'))
            ->paginate($perPage);

        $this->markStartsLater($bookings->getCollection()->all());

        return BookingResource::collection($bookings);
    }

    public function store(
        StoreBookingRequest $request,
        BookingService $bookings,
        RoomAvailabilityService $availability,
        TicketService $tickets,
    ): JsonResponse {
        $user = $request->user();
        $room = Resource::query()->findOrFail($request->integer('resource_id'));

        if (! RoomResource::isBookableByMember($room)) {
            throw new DomainActionException("Cette salle n'est pas réservable.");
        }

        $booking = $request->isExternalBooker()
            ? $this->storeExternal($request, $user, $room, $bookings, $availability, $tickets)
            : $this->storeResident($request, $user, $room, $bookings);

        return $this->respondWith($booking, 201);
    }

    /**
     * Modification d'une réservation du membre (PRD §3.5.5) : mêmes contrôles
     * que la création. L'autorisation (propriétaire, créneau pas encore
     * commencé, `manage-own-booking`) est portée par `UpdateBookingRequest`
     * → `BookingPolicy::update`.
     */
    public function update(
        UpdateBookingRequest $request,
        Booking $booking,
        BookingService $bookings,
        RoomAvailabilityService $availability,
        TicketService $tickets,
    ): JsonResponse {
        $user = $request->user();
        $room = Resource::query()->findOrFail($request->integer('resource_id'));

        // Salle événementielle : lecture seule côté portail (PRD §3.5.4) — ni
        // comme salle d'origine, ni comme destination.
        abort_unless(
            RoomResource::isBookableByMember($room)
                && $booking->resource !== null
                && RoomResource::isBookableByMember($booking->resource),
            403,
            'Cette salle n’est pas réservable depuis le portail : contactez Ecoworking.',
        );

        $booking = $request->isExternalBooker()
            ? $this->updateExternal($request, $user, $booking, $room, $bookings, $availability, $tickets)
            : $bookings->update($booking, [
                'resource_id' => $room->id,
                'starts_at' => CarbonImmutable::parse($request->string('starts_at')->toString()),
                'ends_at' => CarbonImmutable::parse($request->string('ends_at')->toString()),
                'title' => $request->string('title')->toString() ?: null,
            ]);

        return $this->respondWith($booking, 200);
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

    /**
     * Déplacement d'une demi-journée external. Si la demi-journée change, le
     * ticket consommé est restitué puis un ticket disponible re-consommé dans
     * la MÊME transaction (solde inchangé ; 422 si aucun ticket disponible).
     *
     * Une résa posée gratuitement par l'admin (`ticket_id` null) reste gratuite :
     * la déplacer ne débite aucun ticket — déplacer n'est pas acheter.
     */
    private function updateExternal(
        UpdateBookingRequest $request,
        User $user,
        Booking $booking,
        Resource $room,
        BookingService $bookings,
        RoomAvailabilityService $availability,
        TicketService $tickets,
    ): Booking {
        $date = CarbonImmutable::parse($request->string('date')->toString());
        $period = Period::from($request->string('period')->toString());

        if (! $availability->isExternalSlotBookable($room, $date, $period, $booking->getKey())) {
            throw new DomainActionException('Ce créneau n\'est pas réservable (jour non ouvré ou déjà pris).');
        }

        $bounds = $availability->halfDayBounds($date, $period);
        $title = $request->string('title')->toString() ?: null;

        // Comparaison CÔTÉ SQL (piège timezone) : la résa occupe-t-elle déjà
        // exactement cette demi-journée ?
        $sameSlot = Booking::query()
            ->whereKey($booking->getKey())
            ->where('starts_at', $bounds['starts_at'])
            ->where('ends_at', $bounds['ends_at'])
            ->exists();

        return DB::transaction(function () use ($booking, $room, $bounds, $title, $sameSlot, $user, $bookings, $tickets): Booking {
            $ticket = $booking->loadMissing('ticket')->ticket;
            $wasPaid = $ticket instanceof Ticket;

            if (! $sameSlot && $wasPaid) {
                $tickets->restitute($ticket);
                $ticket = $tickets->lockFirstAvailable($user, TicketType::MeetingRoomHalfDay);
            }

            $updated = $bookings->update($booking, [
                'resource_id' => $room->id,
                'starts_at' => $bounds['starts_at'],
                'ends_at' => $bounds['ends_at'],
                'title' => $title,
                'ticket_id' => $ticket?->id,
            ]);

            if (! $sameSlot && $wasPaid && $ticket instanceof Ticket) {
                $tickets->consume($ticket, $updated);
            }

            return $updated;
        });
    }

    private function respondWith(Booking $booking, int $status): JsonResponse
    {
        $booking->load(['resource', 'ticket']);
        $this->markStartsLater([$booking]);

        return (new BookingResource($booking))->response()->setStatusCode($status);
    }

    /**
     * Renseigne `Booking::$startsLater` en UNE requête pour toute la page :
     * la comparaison « le créneau a-t-il commencé ? » doit se faire côté SQL
     * (une ligne fraîchement écrite est relue décalée du fuseau).
     *
     * @param  list<Booking>  $bookings
     */
    private function markStartsLater(array $bookings): void
    {
        if ($bookings === []) {
            return;
        }

        $laterIds = Booking::query()
            ->whereKey(array_map(static fn (Booking $booking): int => $booking->id, $bookings))
            ->startsLater()
            ->pluck('id')
            ->all();

        foreach ($bookings as $booking) {
            $booking->startsLater = in_array($booking->id, $laterIds, true);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRoomAvailabilityRequest;
use App\Http\Resources\RoomResource;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Resource;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Salles du calendrier portail (PRD §3.5.2 à §3.5.4). Lecture seule :
 * catalogue (3 salles de réunion + la salle événementielle, cette dernière
 * non réservable) et disponibilité — par salle et par jour, ou multi-salles
 * sur une plage pour la grille semaine/jour.
 */
final class RoomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeCalendar($request);

        return RoomResource::collection($this->calendarRooms());
    }

    /**
     * Disponibilité multi-salles sur une plage bornée (PRD §3.5.2) : pour
     * chaque salle du calendrier, ses créneaux occupés avec l'occupant
     * (prénom + nom + entité) et le libellé — Q4 : transparence par défaut
     * entre membres. Aucune autre donnée personnelle n'est exposée.
     */
    public function availabilityRange(IndexRoomAvailabilityRequest $request): JsonResponse
    {
        $from = $request->from();
        $to = $request->to()->addDay(); // borne haute exclusive
        $viewer = $request->user();
        $viewerId = $viewer->id;
        $seesOccupants = $this->maySeeOccupants($viewer);

        $rooms = $this->calendarRooms($request->roomIds());

        /** @var Collection<int, Booking> $bookings */
        $bookings = Booking::query()
            ->whereIn('resource_id', $rooms->modelKeys())
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with(['user.memberProfile.company', 'billable'])
            ->orderBy('starts_at')
            ->get();

        $slotsByRoom = $bookings->groupBy('resource_id');
        $editableIds = $this->editableBookingIds($bookings, $viewerId);

        return response()->json([
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            'rooms' => $rooms->map(fn (Resource $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
                'capacity' => $room->capacity,
                'is_bookable' => RoomResource::isBookableByMember($room),
                'slots' => $slotsByRoom->get($room->id, collect())
                    ->map(fn (Booking $booking): array => $this->slot($booking, $viewerId, $seesOccupants, $editableIds))
                    ->values()
                    ->all(),
            ])->values()->all(),
        ]);
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

        $isExternal = $request->user()?->booksAsExternal() === true;

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
            'is_external' => $isExternal,
        ]);
    }

    /**
     * Salles affichées dans le calendrier : salles de réunion + salle
     * événementielle, actives et en service, éventuellement filtrées.
     *
     * @param  list<int>  $onlyIds
     * @return Collection<int, resource>
     */
    private function calendarRooms(array $onlyIds = []): Collection
    {
        return Resource::query()
            ->whereIn('type', [ResourceType::MeetingRoom->value, ResourceType::EventRoom->value])
            ->where('is_active', true)
            ->where('is_out_of_service', false)
            ->when($onlyIds !== [], fn ($query) => $query->whereKey($onlyIds))
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Créneau occupé exposé au calendrier. `booking_id` n'est renseigné que
     * pour ses propres réservations (seules modifiables) ; l'occupant se limite
     * au prénom + nom + entité (aucun email, aucun téléphone) et n'est montré
     * qu'aux membres autorisés à voir l'identité des coworkers.
     *
     * @param  list<int>  $editableIds  réservations du membre encore modifiables
     * @return array<string, mixed>
     */
    private function slot(Booking $booking, int $viewerId, bool $seesOccupants, array $editableIds): array
    {
        $isMine = $booking->user_id === $viewerId;
        // Ses propres réservations restent toujours identifiables (c'est son
        // information), même sans accès à l'identité des autres.
        $identified = $seesOccupants || $isMine;

        return [
            'booking_id' => $isMine ? $booking->id : null,
            'is_mine' => $isMine,
            // Le portail n'ouvre la modale « Modifier / Supprimer » que si la
            // réservation est encore modifiable (délai Q22, comparé côté SQL).
            'cancellable' => in_array($booking->id, $editableIds, true),
            'starts_at' => $booking->starts_at?->toIso8601String(),
            'ends_at' => $booking->ends_at?->toIso8601String(),
            'label' => $identified ? $booking->title : null,
            'occupant' => $identified ? $this->occupant($booking) : null,
        ];
    }

    /**
     * Identifiants des réservations du membre encore modifiables (créneau pas
     * encore commencé) — UNE requête pour toute la plage, comparaison côté SQL
     * (piège timezone : `starts_at->isFuture()` en PHP ment sur les lignes
     * fraîches).
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<int>
     */
    private function editableBookingIds(Collection $bookings, int $viewerId): array
    {
        $mine = $bookings->where('user_id', $viewerId)->modelKeys();

        if ($mine === []) {
            return [];
        }

        /** @var list<int> */
        return Booking::query()->whereKey($mine)->startsLater()->pluck('id')->all();
    }

    /**
     * Le membre a-t-il le droit de voir QUI occupe un créneau ? Q4 (transparence
     * entre membres) s'arrête au périmètre de l'annuaire : l'external n'accède à
     * aucune information d'identité (PRD §3.5.9 / Q16), alors qu'il voit bien le
     * calendrier pour choisir un créneau libre.
     */
    private function maySeeOccupants(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::ViewAnnuaire->value);
    }

    /**
     * Occupant affiché au survol (Q4) : le membre réservant, ou — pour une résa
     * posée par l'admin au nom d'une entité — l'entité juridique.
     *
     * `kind` distingue un membre d'une entité juridique (résa posée par l'admin) :
     * sans lui, le portail ne saurait pas quoi afficher à la place du nom.
     *
     * L'opt-out annuaire (`show_in_directory = false`) n'a **pas** d'effet ici
     * (décision Guillaume 14/09) : il ne porte que sur l'ANNUAIRE, alors que
     * savoir qui occupe une salle est une nécessité opérationnelle du coworking.
     * Le **plan des bureaux** reste, lui, soumis à l'opt-out — deux surfaces
     * distinctes, ne pas aligner l'une sur l'autre sans arbitrage.
     *
     * @return array{kind: string, first_name: ?string, last_name: ?string, company_name: ?string}|null
     */
    private function occupant(Booking $booking): ?array
    {
        $user = $booking->user;

        if ($user instanceof User) {
            return [
                'kind' => 'member',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'company_name' => $user->memberProfile?->company?->name,
            ];
        }

        $billable = $booking->billable;

        if ($billable instanceof Company) {
            return ['kind' => 'entity', 'first_name' => null, 'last_name' => null, 'company_name' => $billable->name];
        }

        return null;
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

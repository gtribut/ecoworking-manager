<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexDeskOccupationsRequest;
use App\Http\Requests\Api\StoreDeskOccupationRequest;
use App\Http\Resources\DeskOccupationResource;
use App\Http\Resources\ResourceResource;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Services\DeskAvailabilityService;
use App\Services\TicketService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Bureaux nomades pour les externals (PRD §3.5.9) : disponibilité, réservation
 * (occupation + ticket), liste et annulation. Toujours auto-scopé sur
 * l'utilisateur authentifié (CLAUDE.md §3.1).
 */
final class DeskController extends Controller
{
    /**
     * Liste des occupations de bureau du membre. Par défaut : à venir
     * (aujourd'hui inclus), chronologique. `?past=1` : historique, plus
     * récent d'abord. Comparaisons de date CÔTÉ SQL (`CURRENT_DATE`) — piège
     * timezone du dépôt, jamais `date->isFuture()` PHP sur une ligne fraîche.
     */
    public function index(IndexDeskOccupationsRequest $request): AnonymousResourceCollection
    {
        $past = $request->boolean('past');
        $upcoming = ! $past;
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        $occupations = DeskOccupation::query()
            ->where('user_id', $request->user()->id)
            ->with(['desk', 'ticket'])
            ->when($upcoming, fn ($query) => $query
                ->where('status', DeskOccupationStatus::Present->value)
                ->whereRaw('date >= CURRENT_DATE')
                ->orderBy('date')
                ->orderBy('id'))
            ->when($past, fn ($query) => $query
                ->whereRaw('date < CURRENT_DATE')
                ->orderByDesc('date')
                ->orderByDesc('id'))
            ->paginate($perPage);

        $this->markCancellable($occupations->getCollection()->all());

        return DeskOccupationResource::collection($occupations);
    }

    /**
     * Disponibilité des bureaux non attitrés pour une date + demi-journée.
     * Jour non ouvré (week-end ou férié français, PRD §3.5.9) : `available:
     * false` + `reason: non_working_day` plutôt qu'une liste vide indistincte
     * d'un « complet » (review 08 — le clic menait sinon à un 422 surprise).
     */
    public function availability(Request $request, DeskAvailabilityService $desks): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'period' => ['required', 'string', 'in:morning,afternoon,full_day'],
        ]);

        $date = CarbonImmutable::parse($request->string('date')->toString());
        $period = Period::from($request->string('period')->toString());

        if (! FrenchHolidays::isWorkingDay($date)) {
            return response()->json([
                'date' => $date->toDateString(),
                'period' => $period->value,
                'available' => false,
                'reason' => 'non_working_day',
                'count' => 0,
                'desks' => [],
            ]);
        }

        $available = $desks->availableDesks($date, $period);

        return response()->json([
            'date' => $date->toDateString(),
            'period' => $period->value,
            'available' => true,
            'reason' => null,
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

        $occupation->load(['desk', 'ticket']);
        $this->markCancellable([$occupation]);

        return (new DeskOccupationResource($occupation))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(DeskOccupation $deskOccupation, DeskAvailabilityService $desks): JsonResponse
    {
        Gate::authorize('delete', $deskOccupation);

        $desks->cancelExternal($deskOccupation);

        return response()->json(['message' => 'Réservation de bureau annulée.']);
    }

    /**
     * Renseigne `DeskOccupation::$cancellable` en UNE requête pour tout le lot
     * (même pattern que `Booking::$startsLater`, BookingController).
     *
     * @param  list<DeskOccupation>  $occupations
     */
    private function markCancellable(array $occupations): void
    {
        if ($occupations === []) {
            return;
        }

        $cancellableIds = DeskOccupation::query()
            ->whereKey(array_map(static fn (DeskOccupation $occupation): int => $occupation->id, $occupations))
            ->cancellable()
            ->pluck('id')
            ->all();

        foreach ($occupations as $occupation) {
            $occupation->cancellable = $occupation->status === DeskOccupationStatus::Present
                && in_array($occupation->id, $cancellableIds, true);
        }
    }
}

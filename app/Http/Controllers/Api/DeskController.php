<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Enums\TicketType;
use App\Http\Controllers\Api\Concerns\MarksScopedFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexDeskAvailabilityRequest;
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
    use MarksScopedFlag;

    /**
     * Liste des occupations de bureau du membre. Par défaut : à venir
     * (aujourd'hui inclus), chronologique. `?past=1` : historique, plus
     * récent d'abord. Comparaison de `date` liée sur `today()->toDateString()`
     * (PHP, Europe/Paris) — jamais `CURRENT_DATE` (SQL) : la session Postgres
     * est en UTC, ce qui décalerait la bascule à 00h-02h heure de Paris
     * (review lot E pt.2, convention `DeskAbsence::scopeNotStartedBefore`).
     */
    public function index(IndexDeskOccupationsRequest $request): AnonymousResourceCollection
    {
        $past = $request->boolean('past');
        $upcoming = ! $past;
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));
        $today = today()->toDateString();

        $occupations = DeskOccupation::query()
            ->where('user_id', $request->user()->id)
            ->with(['desk', 'ticket'])
            ->when($upcoming, fn ($query) => $query
                ->where('status', DeskOccupationStatus::Present->value)
                ->where('date', '>=', $today)
                ->orderBy('date')
                ->orderBy('id'))
            ->when($past, fn ($query) => $query
                ->where('date', '<', $today)
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
    public function availability(IndexDeskAvailabilityRequest $request, DeskAvailabilityService $desks): JsonResponse
    {
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
     * (même pattern que `Booking::$startsLater`). Le scope filtre déjà
     * `status=present` : une occupation annulée ne redevient jamais
     * cancellable (review lot E pt.1).
     *
     * @param  list<DeskOccupation>  $occupations
     */
    private function markCancellable(array $occupations): void
    {
        $this->markWithScope($occupations, 'cancellable', 'cancellable');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAbsenceRequest;
use App\Http\Resources\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Models\User;
use App\Notifications\AbsenceDeclaredNotification;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Présence nomade des résidents (PRD §3.4.6) : calendrier de présence dérivé
 * (bureau attitré − absences) et déclaration/suppression d'absences. Toujours
 * auto-scopé sur l'utilisateur authentifié (le bureau vient de son profil).
 */
final class PresenceController extends Controller
{
    /** Jours présents sur une plage + absences déclarées du membre. */
    public function index(Request $request, PresenceService $presence): JsonResponse
    {
        // Module réservé au membre doté d'un bureau attitré (PRD §2.5) : refus
        // explicite plutôt qu'un calendrier vide trompeur pour un `additional`.
        Gate::authorize('viewAny', DeskAbsence::class);

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = CarbonImmutable::parse($request->string('from')->toString());
        $to = CarbonImmutable::parse($request->string('to')->toString());

        return response()->json([
            'present_days' => $presence->presentDays($user, $from, $to),
            'absences' => DeskAbsenceResource::collection(
                $user->deskAbsences()->orderBy('date_start')->get()
            )->resolve(),
        ]);
    }

    public function store(StoreAbsenceRequest $request, PresenceService $presence): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $absence = $presence->declareAbsence([
            'user' => $user,
            'date_start' => CarbonImmutable::parse($data['date_start']),
            'date_end' => isset($data['date_end']) ? CarbonImmutable::parse($data['date_end']) : null,
            'period' => Period::from($data['period'] ?? Period::FullDay->value),
            'recurrence_type' => DeskAbsenceRecurrence::from($data['recurrence_type'] ?? DeskAbsenceRecurrence::None->value),
            'recurrence_day_of_week' => $data['recurrence_day_of_week'] ?? null,
            'created_by' => $user->id,
        ]);

        // Notification admin systématique (PRD Q25) : visibilité sur les bureaux
        // libérés. In-app uniquement (non critique), en queue.
        Notification::send(
            User::role(Role::Admin->value)->get(),
            new AbsenceDeclaredNotification($absence, $user),
        );

        return (new DeskAbsenceResource($absence))->response()->setStatusCode(201);
    }

    public function destroy(DeskAbsence $absence): JsonResponse
    {
        Gate::authorize('delete', $absence);

        $absence->delete();

        return response()->json(['message' => 'Absence supprimée.']);
    }
}

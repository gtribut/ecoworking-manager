<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAbsenceRequest;
use App\Http\Requests\Api\UpdateAbsenceRequest;
use App\Http\Resources\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Models\User;
use App\Notifications\AbsenceDeclaredNotification;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Présence nomade des résidents (PRD §3.4.6) : bureau attitré, calendrier de
 * présence dérivé (bureau − absences) et déclaration/modification/suppression
 * d'absences. Toujours auto-scopé sur l'utilisateur authentifié (le bureau
 * vient de son profil, jamais du client).
 */
final class PresenceController extends Controller
{
    /** Bureau attitré, jours présents sur une plage + absences du membre. */
    public function index(Request $request, PresenceService $presence): JsonResponse
    {
        // Module réservé au membre doté d'un bureau attitré (PRD §2.5) : refus
        // explicite plutôt qu'un calendrier vide trompeur pour un `additional`.
        Gate::authorize('viewAny', DeskAbsence::class);

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'all' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $from = CarbonImmutable::parse($request->string('from')->toString());
        $to = CarbonImmutable::parse($request->string('to')->toString());

        // Par défaut : absences à venir ou en cours (PRD §3.4.6, « liste de mes
        // absences planifiées à venir »). `?all=1` rend l'historique complet.
        $absences = $user->deskAbsences()
            ->unless($request->boolean('all'), fn ($query) => $query->upcoming())
            ->orderBy('date_start')
            ->get();

        $this->markEditability($absences);

        $desk = $user->memberProfile?->desk;

        return response()->json([
            'desk' => $desk === null ? null : [
                'id' => $desk->id,
                'name' => $desk->name,
                'floor' => $desk->floor,
                'svg_desk_id' => $desk->svg_desk_id,
            ],
            'present_days' => $presence->presentDays($user, $from, $to),
            'absences' => DeskAbsenceResource::collection($absences)->resolve(),
        ]);
    }

    public function store(StoreAbsenceRequest $request, PresenceService $presence): JsonResponse
    {
        $user = $request->user();

        $absence = $presence->declareAbsence([
            'user' => $user,
            ...$this->absenceAttributes($request->validated()),
            'created_by' => $user->id,
        ]);

        // Notification admin systématique (PRD Q25) : visibilité sur les bureaux
        // libérés. In-app uniquement (non critique), en queue. Déclenchée ICI,
        // et donc jamais quand l'admin saisit lui-même depuis le back-office.
        Notification::send(
            User::role(Role::Admin->value)->get(),
            new AbsenceDeclaredNotification($absence, $user),
        );

        $this->markEditability(new Collection([$absence]));

        return (new DeskAbsenceResource($absence))->response()->setStatusCode(201);
    }

    /** Modification bornée au début de l'absence (DeskAbsencePolicy::update). */
    public function update(UpdateAbsenceRequest $request, DeskAbsence $absence, PresenceService $presence): JsonResponse
    {
        $attributes = $this->absenceAttributes($request->validated());

        // Une note interne saisie par l'accueil n'est PAS renvoyée au membre
        // (cf. DeskAbsenceResource) : il ne peut donc pas l'écraser en
        // renvoyant le formulaire, sans quoi elle disparaîtrait en silence.
        if ($absence->created_by !== $absence->user_id) {
            $attributes['notes'] = $absence->notes;
        }

        $updated = $presence->updateAbsence($absence, $attributes);

        $this->markEditability(new Collection([$updated]));

        return (new DeskAbsenceResource($updated))->response();
    }

    public function destroy(DeskAbsence $absence): JsonResponse
    {
        Gate::authorize('delete', $absence);

        $absence->delete();

        return response()->json(['message' => 'Absence supprimée.']);
    }

    /**
     * Payload validé → attributs métier du PresenceService.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function absenceAttributes(array $data): array
    {
        return [
            'date_start' => CarbonImmutable::parse((string) $data['date_start']),
            'date_end' => isset($data['date_end']) ? CarbonImmutable::parse((string) $data['date_end']) : null,
            'period' => Period::from((string) ($data['period'] ?? Period::FullDay->value)),
            'recurrence_type' => DeskAbsenceRecurrence::from((string) ($data['recurrence_type'] ?? DeskAbsenceRecurrence::None->value)),
            'recurrence_day_of_week' => isset($data['recurrence_day_of_week']) ? (int) $data['recurrence_day_of_week'] : null,
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
        ];
    }

    /**
     * Renseigne `can_edit` / `can_delete` en UNE requête pour toute la page :
     * « l'absence a-t-elle commencé ? » se compare CÔTÉ SQL sur les colonnes
     * DATE (une ligne fraîchement écrite est relue décalée du fuseau). Les deux
     * fenêtres sont identiques (jour de début inclus, cf. DeskAbsencePolicy),
     * mais restent deux drapeaux distincts dans le contrat d'API.
     *
     * @param  Collection<int, DeskAbsence>  $absences
     */
    private function markEditability(Collection $absences): void
    {
        if ($absences->isEmpty()) {
            return;
        }

        $openIds = DeskAbsence::query()
            ->whereKey($absences->modelKeys())
            ->notStartedBefore()
            ->pluck('id')
            ->all();

        foreach ($absences as $absence) {
            $open = in_array($absence->id, $openIds, true);
            $absence->canEdit = $open;
            $absence->canDelete = $open;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\DirectoryEntryResource;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\FloorPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Annuaire des coworkers + plan des étages (C12.5, PRD §3.7).
 *
 * Accès réservé aux détenteurs de `view-annuaire` (resident/additional/staff —
 * PAS les external, PRD §3.7.1 / §2.5) ; l'admin passe toujours. L'annuaire ne
 * liste QUE les profils opt-in (`show_in_directory`) actifs, en projection
 * minimale ({@see DirectoryEntryResource}) — pas d'email ni de téléphone.
 */
final class DirectoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeDirectory($request->user());

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($validated['q'] ?? '');

        $profiles = MemberProfile::query()
            ->inDirectory()
            ->active()
            // Exclut les comptes soft-deleted/anonymisés (scope SoftDeletes).
            ->whereHas('user')
            ->with(['user', 'company', 'desk'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function (Builder $sub) use ($like): void {
                    $sub->whereHas('user', function (Builder $user) use ($like): void {
                        $user->where('first_name', 'ilike', $like)
                            ->orWhere('last_name', 'ilike', $like);
                    })
                        ->orWhereHas('company', fn (Builder $company) => $company->where('legal_name', 'ilike', $like))
                        ->orWhere('job_title', 'ilike', $like);
                });
            })
            ->orderBy(User::select('last_name')->whereColumn('users.id', 'member_profiles.user_id'))
            ->orderBy(User::select('first_name')->whereColumn('users.id', 'member_profiles.user_id'))
            ->paginate(24);

        return DirectoryEntryResource::collection($profiles);
    }

    /** Occupation du jour bureau par bureau, pour colorer le plan SVG. */
    public function floorPlan(Request $request, FloorPlanService $plan): JsonResponse
    {
        $user = $request->user();
        $this->authorizeDirectory($user);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'])
            : CarbonImmutable::today();

        return response()->json($plan->forDate($date, $user));
    }

    /** PRD §3.7.1 : module inaccessible aux `external` (403, pas de fuite). */
    private function authorizeDirectory(User $user): void
    {
        abort_unless(
            $user->isAdmin() || $user->can(Permission::ViewAnnuaire->value),
            403,
            'Vous n\'avez pas accès à l\'annuaire.',
        );
    }
}

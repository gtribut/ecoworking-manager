<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\MemberProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil membre côté portail (PRD §3.4). Toujours auto-scopé sur l'utilisateur
 * authentifié : aucun identifiant n'est accepté du client, un membre ne peut
 * donc consulter/éditer que son propre profil (CLAUDE.md §3.1).
 */
final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Préférences portées par `users` (thème + toggles notif) ; le reste va
        // sur le profil membre.
        $userPrefs = array_intersect_key($data, array_flip(['theme', 'notify_email', 'notify_in_app']));
        if ($userPrefs !== []) {
            $user->fill($userPrefs)->save();
        }

        $profileData = array_intersect_key($data, array_flip([
            'job_title', 'bio', 'interests', 'linkedin_url', 'website_url',
            'birth_date', 'show_in_directory', 'newsletter_opt_in',
        ]));

        if ($profileData !== []) {
            $profile = $user->memberProfile;

            if ($profile === null) {
                // Cas atypique (billing_contact pur / admin sans bureau) : aucun
                // profil membre à éditer (PRD §3.4.3, bloc masqué).
                return response()->json([
                    'message' => 'Aucun profil membre éditable pour ce compte.',
                ], 409);
            }

            $profile->fill($profileData)->save();
        }

        return response()->json($this->payload($user->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $profile = $user->memberProfile()->with(['desk', 'company'])->first();

        return [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'theme' => $user->theme,
                'notify_email' => $user->notify_email,
                'notify_in_app' => $user->notify_in_app,
            ],
            'profile' => $profile === null ? null : (new MemberProfileResource($profile))->resolve(),
            'company' => $profile?->company === null
                ? null
                : (new CompanyResource($profile->company))->resolve(),
        ];
    }
}

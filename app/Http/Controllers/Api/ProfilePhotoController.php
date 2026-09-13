<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProfilePhotoRequest;
use App\Models\MemberProfile;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo de profil du membre authentifié (PRD §3.4.2). Auto-scopé : aucun
 * identifiant n'est accepté du client, un membre ne peut donc toucher qu'à sa
 * propre photo (CLAUDE.md §3.1).
 */
final class ProfilePhotoController extends Controller
{
    public function store(StoreProfilePhotoRequest $request, ProfilePhotoService $photos): JsonResponse
    {
        $profile = $this->profile($request);

        if ($profile === null) {
            return $this->noProfile();
        }

        $photos->store($profile, $request->file('photo'));

        return response()->json([
            'photo' => ProfilePhotoService::urls($profile->photo_path, $profile->user_id),
        ]);
    }

    public function destroy(Request $request, ProfilePhotoService $photos): JsonResponse
    {
        $profile = $this->profile($request);

        if ($profile === null) {
            return $this->noProfile();
        }

        $photos->delete($profile);

        return response()->json(['photo' => null]);
    }

    /** Cas atypique (billing_contact pur / admin sans bureau) : pas de profil. */
    private function profile(Request $request): ?MemberProfile
    {
        return $request->user()->memberProfile;
    }

    private function noProfile(): JsonResponse
    {
        return response()->json([
            'message' => 'Aucun profil membre éditable pour ce compte.',
        ], 409);
    }
}

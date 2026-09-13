<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lecture d'une photo de profil (PRD §3.4.2 / §3.7.3).
 *
 * Les fichiers ne sont JAMAIS publics : ils transitent par cet endpoint
 * authentifié (`auth:sanctum`), autorisé par {@see UserPolicy::viewPhoto()}.
 * Tout refus répond 404 et non 403 : un membre hors annuaire ne doit même pas
 * apprendre que la personne a une photo (moindre exposition).
 *
 * Cache privé court : l'image est personnelle, jamais mutualisable dans un
 * cache partagé, mais une page d'annuaire ne doit pas retélécharger 24 photos.
 */
final class UserPhotoController extends Controller
{
    private const int CACHE_SECONDS = 900;

    public function __invoke(Request $request, User $user, int $size, ProfilePhotoService $photos): StreamedResponse
    {
        abort_unless(Gate::allows('viewPhoto', $user), 404);

        $prefix = $user->memberProfile?->photo_path;
        abort_if($prefix === null, 404);

        $path = $photos->resolve($prefix, $size);
        abort_if($path === null, 404);

        return $photos->disk()->response($path, null, [
            'Cache-Control' => 'private, max-age='.self::CACHE_SECONDS,
        ]);
    }
}

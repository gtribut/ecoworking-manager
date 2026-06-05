<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Login admin additionnel via Google OAuth (Socialite) — C2.2, BRIEF §8, ADR-0009.
 *
 * Politique de sécurité (stricte) :
 *  - **admin-only** : on ne connecte qu'un compte existant portant le rôle `admin` ;
 *  - **domaine restreint** : l'email vérifié doit appartenir au Workspace autorisé
 *    (`services.google.hosted_domain`) ;
 *  - **match par email** : aucune création de compte automatique (cohérent avec
 *    l'absence d'inscription self-service, PRD §3.2).
 *
 * Toute condition non remplie → pas de session, redirection avec erreur.
 */
final class GoogleOAuthController extends Controller
{
    /** Démarre le flow OAuth (redirige vers Google). */
    public function redirect(): RedirectResponse
    {
        $driver = Socialite::driver('google');

        if ($hostedDomain = config('services.google.hosted_domain')) {
            // `hd` n'est qu'un indice d'UI côté Google : revérifié au callback.
            $driver->with(['hd' => $hostedDomain]);
        }

        return $driver->redirect();
    }

    /** Reçoit le retour de Google, applique la politique, ouvre la session si OK. */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->deny('Échec de l\'authentification Google.');
        }

        if (! $this->emailIsVerifiedAndAllowed($googleUser)) {
            return $this->deny('Compte Google non autorisé pour cet espace.');
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user === null || ! $user->isAdmin()) {
            // Pas d'auto-provisioning : seuls les admins existants sont connectables.
            return $this->deny('Aucun compte administrateur ne correspond à ce compte Google.');
        }

        Auth::guard('web')->login($user);

        return redirect()->intended(config('fortify.home', '/'));
    }

    /** Email vérifié par Google ET appartenant au domaine Workspace autorisé. */
    private function emailIsVerifiedAndAllowed(SocialiteUser $googleUser): bool
    {
        $email = $googleUser->getEmail();

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $raw = $googleUser->user ?? [];

        if (! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $hostedDomain = config('services.google.hosted_domain');

        if ($hostedDomain && ! Str::endsWith(Str::lower($email), '@'.Str::lower($hostedDomain))) {
            return false;
        }

        return true;
    }

    private function deny(string $message): RedirectResponse
    {
        Auth::guard('web')->logout();

        return redirect()->to(config('fortify.home', '/'))
            ->withErrors(['google' => $message], 'oauth');
    }
}

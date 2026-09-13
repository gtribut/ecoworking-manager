<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Invitation d'accueil (PRD §3.2) : à la création d'un compte par l'admin, le
 * membre reçoit un lien de DÉFINITION de son mot de passe — jamais un mot de
 * passe en clair, ni par mail ni dans les logs.
 *
 * Le jeton vient du broker `welcome` (table dédiée, 3 jours, cf. config/auth.php)
 * et se consomme sur la MÊME page de la SPA que « mot de passe oublié »
 * (`/reset-password/{token}`), avec le drapeau `welcome=1` qui aiguille vers
 * `POST /reset-password/welcome`. Un jeton d'accueil n'ouvre donc pas le flux
 * de reset ordinaire, et réciproquement.
 */
final class WelcomeInvitationService
{
    /** Validité du lien, en jours (miroir de `auth.passwords.welcome.expire`). */
    public const int TTL_DAYS = 3;

    /** Nom du broker dédié — jamais celui de « mot de passe oublié ». */
    public const string BROKER = 'welcome';

    /**
     * Un jeton d'accueil a-t-il été émis il y a moins de `throttle` secondes ?
     * S'appuie sur le dépôt du broker (`auth.passwords.welcome.throttle`) :
     * c'est ce qui rend cette clé de config effective, `createToken()` ne la
     * consultant pas de lui-même.
     */
    public function recentlySent(User $user): bool
    {
        return $this->broker()->getRepository()->recentlyCreatedToken($user);
    }

    /**
     * Émet un nouveau jeton d'accueil (invalidant le précédent) et envoie le
     * mail en queue. Retourne false sans rien faire si le compte est inéligible
     * (anonymisé, supprimé) ou si un envoi vient d'avoir lieu — l'action admin
     * « Renvoyer » est déclenchable en boucle, et chaque envoi périmant le lien
     * précédent, un double-clic condamnerait le lien qui vient de partir.
     */
    public function send(User $user): bool
    {
        if ($user->anonymized_at !== null || $user->trashed()) {
            return false;
        }

        if ($this->recentlySent($user)) {
            return false;
        }

        $token = $this->broker()->createToken($user);

        $url = FortifyServiceProvider::portalUrl(
            '/reset-password/'.$token.'?email='.urlencode($user->email).'&welcome=1',
        );

        Mail::to($user->email)->queue(new WelcomeMail($user->first_name, $url, self::TTL_DAYS));

        return true;
    }

    private function broker(): PasswordBroker
    {
        return Password::broker(self::BROKER);
    }
}

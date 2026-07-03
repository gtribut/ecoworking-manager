<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Connexion membre par magic link (C12.8a, PRD §3.2 / ADR-0011).
 *
 * Double protection sur le lien : URL signée temporaire (intégrité + expiration
 * vérifiables sans DB) ET jeton persisté hashé (usage unique + invalidation
 * ciblée). Réservé aux membres : un compte admin ne reçoit JAMAIS de lien et un
 * lien forgé pour un admin ne connecte pas (l'admin garde mdp + 2FA obligatoire).
 *
 * Anti-énumération : `request()` ne signale jamais à l'appelant si l'email
 * correspond à un compte — le controller renvoie une réponse générique.
 */
final class MagicLinkService
{
    /** Durée de vie du lien (PRD §3.2 : 15 minutes). */
    public const int TTL_MINUTES = 15;

    /**
     * Demande d'un lien de connexion. Silencieux quel que soit le résultat :
     * email inconnu, compte admin, compte anonymisé → aucun envoi, aucun signal.
     */
    public function request(string $email): void
    {
        // `lowercase_usernames` (Fortify) : les emails sont stockés en minuscules.
        $user = User::where('email', Str::lower($email))->first();

        if ($user === null || ! $this->eligible($user)) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $url = URL::temporarySignedRoute(
            'magic-link.consume',
            now()->addMinutes(self::TTL_MINUTES),
            ['token' => $token],
        );

        // Mailable en queue : l'envoi ne bloque jamais la requête (CLAUDE.md §7).
        Mail::to($user->email)->queue(new MagicLinkMail($url, self::TTL_MINUTES));
    }

    /**
     * Consomme un jeton : valide (non utilisé, non expiré, compte éligible) →
     * marqué utilisé et retourne l'utilisateur à connecter ; sinon null, sans
     * distinguer la cause (rien d'exploitable côté client).
     */
    public function consume(string $token): ?User
    {
        $record = MagicLinkToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return null;
        }

        // Marquage atomique : deux consommations simultanées du même jeton ne
        // peuvent pas réussir toutes les deux (usage unique strict).
        $consumed = MagicLinkToken::whereKey($record->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($consumed === 0) {
            return null;
        }

        $user = $record->user;

        // Le jeton est consommé même si le compte n'est plus éligible (devenu
        // admin, supprimé, anonymisé) : il ne resservira pas.
        if ($user === null || ! $this->eligible($user)) {
            return null;
        }

        return $user;
    }

    /** Invalide tous les jetons d'un utilisateur (changement de mot de passe…). */
    public function invalidateFor(User $user): void
    {
        MagicLinkToken::where('user_id', $user->id)->delete();
    }

    /** Membre portail actif uniquement : jamais d'admin, jamais de compte anonymisé. */
    private function eligible(User $user): bool
    {
        return ! $user->isAdmin() && $user->anonymized_at === null;
    }
}

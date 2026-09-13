<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * Anonymisation RGPD d'un utilisateur — « droit à l'oubli » (PRD §5.6,
 * CLAUDE.md §3.4). Écrase la PII (compte + profil membre), révoque tous les
 * accès (mot de passe, 2FA, sessions, tokens) puis soft-delete le compte.
 *
 * Données volontairement CONSERVÉES (obligation légale / PRD §5.6-3) :
 * factures et lignes (10 ans, snapshots `billing_*` inclus), paiements,
 * historique d'audit. L'entrée d'audit « anonymized » est ajoutée SANS
 * aucune PII : le logging du modèle est désactivé pendant l'écrasement pour
 * ne pas journaliser les anciennes valeurs en diff (`attribute_changes`).
 */
final class AnonymizeUserService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ProfilePhotoService $photos,
    ) {}

    /**
     * @throws RuntimeException si l'utilisateur est déjà anonymisé
     */
    public function anonymize(User $user, ?User $actor = null): User
    {
        return $this->db->transaction(function () use ($user, $actor): User {
            // Verrou + re-lecture DANS la transaction (y compris soft-deleted) :
            // deux anonymisations concurrentes ne doivent pas se croiser.
            /** @var User $user */
            $user = User::withTrashed()->lockForUpdate()->findOrFail($user->getKey());

            if ($user->anonymized_at !== null) {
                throw new RuntimeException('Utilisateur déjà anonymisé.');
            }

            $originalEmail = $user->email;

            $this->anonymizeProfile($user);
            $this->anonymizeAccount($user);
            $this->revokeAccess($user, $originalEmail);

            // Soft delete → reconnexion impossible (PRD §5.6-4). Le logging du
            // modèle est resté désactivé : pas d'événement `deleted` audité.
            $user->delete();

            // Entrée d'audit explicite, sans PII ni diff (PRD §5.6-2).
            activity()
                ->performedOn($user)
                ->causedBy($actor)
                ->event('anonymized')
                ->log('Membre anonymisé le '.now()->format('d/m/Y'));

            return $user;
        });
    }

    /**
     * Écrase la PII du compte et révoque les credentials. `disableLogging()`
     * est indispensable : first_name/last_name/email sont dans la liste
     * blanche d'audit — sans lui, les anciennes valeurs partiraient dans le
     * diff `attribute_changes` de l'activité `updated`.
     */
    private function anonymizeAccount(User $user): void
    {
        $user->disableLogging();

        $user->forceFill([
            'first_name' => 'Utilisateur',
            'last_name' => 'anonymisé',
            // Hash déterministe non réversible, unique par compte (PRD §5.6-2).
            'email' => sprintf('deleted-%s@ecoworking.invalid', hash('sha256', (string) $user->getKey())),
            'email_verified_at' => null,
            // Cast `hashed` → mot de passe aléatoire inutilisable.
            'password' => Str::random(64),
            'remember_token' => Str::random(60),
            // 2FA Fortify (portail) + 2FA Filament (admin) : secrets supprimés.
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            // Token iCal : URL de capacité → révocation immédiate des flux.
            'calendar_token' => null,
            'last_login_at' => null,
            'anonymized_at' => now(),
        ])->save();
    }

    /** Écrase la PII du profil membre (annuaire) et supprime la photo du disque. */
    private function anonymizeProfile(User $user): void
    {
        $profile = $user->memberProfile;

        if ($profile === null) {
            return;
        }

        // Supprime les fichiers du disque (3 rendus) ET remet `photo_path` à
        // null — cf. ProfilePhotoService, qui gère aussi les photos déposées
        // par l'admin avant le portail (chemin de fichier simple).
        $this->photos->delete($profile);

        $profile->forceFill([
            'photo_path' => null,
            'birth_date' => null,
            'job_title' => null,
            'bio' => null,
            'interests' => null,
            'linkedin_url' => null,
            'website_url' => null,
            'admin_notes' => null,
            'show_in_directory' => false,
            'newsletter_opt_in' => false,
        ])->save();
    }

    /** Révoque tokens API, sessions actives et jetons de reset liés à l'ancien email. */
    private function revokeAccess(User $user, string $originalEmail): void
    {
        // Tokens Sanctum (défensif : la SPA est cookie-based, mais on purge).
        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();

        // Sessions actives (driver database) → déconnexion immédiate partout.
        $this->db->table('sessions')->where('user_id', $user->getKey())->delete();

        // Jetons de réinitialisation adressés à l'ancien email.
        $this->db->table('password_reset_tokens')->where('email', $originalEmail)->delete();
    }
}

<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Cache;
use Pest\Browser\Api\AwaitableWebpage;

/**
 * Helpers de la suite e2e admin (tests/Browser, cf. docs/testing-e2e.md).
 * Chargés via autoload-dev (composer.json) — inertes hors des tests Browser.
 */

/** Secret TOTP contrôlé (base32) — identique à celui de FilamentPanelTest. */
const E2E_ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

/**
 * Crée un admin prêt pour le login navigateur : mot de passe connu de la
 * factory (`password`) + 2FA TOTP déjà configurée avec un secret contrôlé
 * (sinon Filament force le setup 2FA au premier login).
 */
function createAdminWithTotp(): User
{
    $admin = User::factory()->admin()->create([
        'first_name' => 'Admin',
        'last_name' => 'E2E',
    ]);
    $admin->saveAppAuthenticationSecret(E2E_ADMIN_TOTP_SECRET);
    $admin->saveAppAuthenticationRecoveryCodes(['aaaa-1111-bbbb', 'cccc-2222-dddd']);

    return $admin;
}

/**
 * Code TOTP courant pour le secret de l'admin — même horloge que le serveur
 * in-process (fenêtre de validation Filament : 8 périodes).
 */
function currentTotpCode(User $admin): string
{
    return app(AppAuthentication::class)->getCurrentCode($admin);
}

/**
 * Parcours de connexion admin complet (email + mot de passe puis challenge
 * TOTP) — le même que celui vérifié par AdminLoginTest, réutilisé par les
 * autres tests Browser pour ouvrir une session réelle.
 */
function loginToAdminPanel(User $admin): AwaitableWebpage
{
    // Anti-rejeu TOTP de Filament : le dernier code utilisé est mémorisé en
    // cache par id utilisateur. Le cache `file` persiste d'un test à l'autre
    // alors que RefreshDatabase redistribue les mêmes ids — deux logins dans
    // la même fenêtre de 30 s seraient rejetés (« code invalide »). Purge.
    Cache::flush();

    return visit('/admin/login')
        ->type('input[type=email]', $admin->email)
        ->type('input[type=password]', 'password')
        ->click('Connexion')
        ->waitForText('Vérifier votre identité')
        ->type('input[autocomplete=one-time-code]', currentTotpCode($admin))
        ->click('Confirmer la connexion')
        ->waitForText('Tableau de bord');
}

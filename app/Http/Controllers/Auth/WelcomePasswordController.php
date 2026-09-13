<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\Auth\WelcomeInvitationService;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Controllers\NewPasswordController;

/**
 * Définition initiale du mot de passe depuis l'email d'accueil (PRD §3.2).
 *
 * Réutilise INTÉGRALEMENT le flux Fortify de réinitialisation (validation,
 * `ResetsUserPasswords`, connexion automatique, réponses 200/422) : seul le
 * dépôt de jetons change — broker `welcome`, table dédiée, 3 jours. Les deux
 * flux restent étanches : un jeton d'accueil est introuvable pour
 * `POST /reset-password`, un jeton de reset l'est pour cette route.
 */
final class WelcomePasswordController extends NewPasswordController
{
    protected function broker(): PasswordBroker
    {
        return Password::broker(WelcomeInvitationService::BROKER);
    }
}

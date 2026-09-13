<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Réponse « échec » de la demande de lien de réinitialisation, rendue
 * STRICTEMENT identique au succès (PRD §3.2 : aucun message ne révèle si
 * l'email existe). Fortify renvoie par défaut un 422 « Aucun utilisateur… »
 * (email inconnu) ou « Veuillez patienter… » (throttle, donc compte existant) :
 * deux oracles d'énumération. Ici, tout échec métier → même 200 générique ;
 * la validation de format (422 `email`) reste, elle, en amont dans Fortify.
 */
final class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => trans('passwords.sent')]);
    }
}

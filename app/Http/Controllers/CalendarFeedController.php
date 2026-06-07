<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IcsCalendarService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Flux iCal d'abonnement (PRD §3.5.8, C9.2). Routes PUBLIQUES authentifiées par
 * un **token secret en URL** (capacité), et non par session : un client agenda
 * (Google/Apple) ne peut pas porter le cookie Sanctum. Le token identifie le
 * membre et borne le périmètre du flux ; sa régénération révoque les anciens
 * abonnements. Réservations de salles uniquement.
 */
final class CalendarFeedController extends Controller
{
    public function __construct(private readonly IcsCalendarService $ics) {}

    /** Flux « Mes réservations » du porteur du token. */
    public function mine(string $token): Response
    {
        $user = $this->resolveUser($token);

        return $this->icsResponse($this->ics->forUser($user), 'mes-reservations.ics');
    }

    /** Flux « Réservations de mon entité » (membres des mêmes entités). */
    public function entity(string $token): Response
    {
        $user = $this->resolveUser($token);

        return $this->icsResponse($this->ics->forEntity($user), 'reservations-entite.ics');
    }

    private function resolveUser(string $token): User
    {
        // Token opaque non devinable : un token inconnu = 404 (ni énumération,
        // ni fuite d'existence). Pas de fallback sur l'utilisateur courant.
        abort_if($token === '' || strlen($token) < 16, 404);

        return User::query()->where('calendar_token', $token)->firstOrFail();
    }

    private function icsResponse(string $body, string $filename): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            // Pas de cache agressif : les clients agenda re-pollent à leur rythme.
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}

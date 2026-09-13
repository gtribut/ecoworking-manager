<?php

use App\Exceptions\BookingConflictException;
use App\Exceptions\DomainActionException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum mode SPA (ADR-0003) : rend le groupe `api` stateful pour les
        // domaines déclarés dans SANCTUM_STATEFUL_DOMAINS → auth par session/cookie
        // + CSRF, plutôt que par token. Indispensable pour que `auth:sanctum`
        // résolve l'utilisateur via la session du portail.
        $middleware->statefulApi();

        // Lie chaque session au hash du mot de passe de son propriétaire : un
        // changement de mot de passe (qui re-hash, cf. UpdateUserPassword +
        // Auth::logoutOtherDevices) rend toutes les AUTRES sessions caduques
        // dès leur requête suivante. Sans ce middleware, `logoutOtherDevices`
        // ne déconnecte personne : rien ne compare jamais les deux hashes.
        //
        // Sur `web` (login, logout, changement de mot de passe, magic link) :
        // le groupe ne l'avait pas du tout. Sur `api` : Sanctum l'injecte déjà
        // dans son pipeline `frontendMiddleware()` (config/sanctum.php), mais
        // seulement pour les requêtes reconnues comme venant du front — on
        // l'ajoute donc explicitement pour que la garantie ne dépende pas de
        // la présence d'un en-tête Origin/Referer. Le doublon est sans effet
        // (contrôle idempotent).
        //
        // C'est la variante Sanctum, pas celle d'Illuminate : elle cible
        // explicitement les gardes de session de `config('sanctum.guard')` au
        // lieu de s'en remettre au driver par défaut. Celle d'Illuminate
        // appelle `viaRemember()` sur ce driver et explose (BadMethodCall) dès
        // qu'il n'est pas une SessionGuard — ce qui arrive sur les flux iCal,
        // authentifiés par jeton d'URL.
        $middleware->web(append: [AuthenticateSession::class]);
        $middleware->api(append: [AuthenticateSession::class]);

        // Aucune route web nommée `login` (le portail est une SPA, l'admin
        // Filament gère sa propre redirection de login) : sans ceci, un invité
        // naviguant en HTML sur une route `auth:sanctum` (ex. /api/user dans un
        // navigateur) provoquerait une RouteNotFoundException 500 au lieu d'un
        // 401 (rendu JSON pour /api/* via shouldRenderJsonWhen ci-dessous).
        $middleware->redirectGuestsTo(fn (): null => null);

        // Rate limiting du groupe `api` : Laravel 11+ n'en applique AUCUN par
        // défaut. Le limiteur `api` (60 req/min par user|IP) est défini dans
        // AppServiceProvider. Login/2FA ont leurs limiteurs Fortify dédiés.
        $middleware->throttleApi();

        // Alias des middlewares spatie/laravel-permission (gating par rôle/permission
        // sur les routes — PRD §2.7). Les Policies restent la source de vérité de
        // l'isolation des données ; ces middlewares ne sont qu'un filtre d'accès route.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Rendre les exceptions en JSON pour /api/* (même sans en-tête Accept) ET
        // pour toute requête qui demande explicitement du JSON (SPA, endpoints
        // Fortify appelés en application/json) — sinon une ValidationException sur
        // /login repartirait en redirect HTML au lieu d'un 422 JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Issues métier attendues (conflit de créneau, action portail invalide) :
        // ce sont des résultats de contrôle de flux, pas des erreurs à logguer.
        $exceptions->dontReport([
            BookingConflictException::class,
            DomainActionException::class,
        ]);

        // Conflit de créneau (anti-double-booking §5.4) → 409 Conflict.
        $exceptions->render(fn (BookingConflictException $e) => new JsonResponse(
            ['message' => $e->getMessage()], 409,
        ));

        // Erreur métier d'action portail (ticket indisponible, bureau occupé…) → 422.
        $exceptions->render(fn (DomainActionException $e) => new JsonResponse(
            ['message' => $e->getMessage()], 422,
        ));

        // Remontée des exceptions non gérées vers Sentry (C10.1). No-op si
        // SENTRY_LARAVEL_DSN est vide (dev/test) : rien n'est transmis.
        Integration::handles($exceptions);
    })->create();

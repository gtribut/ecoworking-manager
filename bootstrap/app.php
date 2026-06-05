<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
    })->create();

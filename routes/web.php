<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\PortalSpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Flux iCal d'abonnement (C9.2, PRD §3.5.8)
|--------------------------------------------------------------------------
|
| Routes publiques authentifiées par un token secret en URL (les clients
| agenda ne portent pas de session). Servies sur le domaine portail (prod `.fr`
| + dev local `.test`) via config('domains.portal') ; sans contrainte en test.
|
*/
$calendarFeeds = function (): void {
    Route::controller(CalendarFeedController::class)
        ->prefix('calendar/{token}')
        ->name('calendar.')
        ->group(function (): void {
            Route::get('mine.ics', 'mine')->name('mine');
            Route::get('entity.ics', 'entity')->name('entity');
        });
};

if ($portalDomain = config('domains.portal')) {
    Route::domain($portalDomain)->group($calendarFeeds);
} else {
    $calendarFeeds();
}

/*
|--------------------------------------------------------------------------
| Auth admin — Google OAuth (C2.2, BRIEF §8 / ADR-0009)
|--------------------------------------------------------------------------
|
| Login Google additionnel réservé aux admins (match par email, domaine
| restreint, pas d'auto-provisioning). Contraint au sous-domaine admin (prod
| `.fr` + dev local `.test`) via config('domains.admin') ; sans contrainte en test.
|
*/
$adminAuthRoutes = function (): void {
    Route::controller(GoogleOAuthController::class)
        ->prefix('auth/google')
        ->name('auth.google.')
        ->group(function (): void {
            Route::get('redirect', 'redirect')->name('redirect');
            Route::get('callback', 'callback')->name('callback');
        });
};

if ($adminDomain = config('domains.admin')) {
    Route::domain($adminDomain)->group($adminAuthRoutes);
} else {
    $adminAuthRoutes();
}

/*
|--------------------------------------------------------------------------
| SPA portail — catch-all (C12.1, BRIEF §6 / ADR-0004)
|--------------------------------------------------------------------------
|
| Sert le shell HTML de la SPA (assets injectés depuis le manifest Vite) sur
| toutes les routes GET du domaine portail : le routing applicatif est géré
| côté client (React Router). Exclusions par regex — jamais interceptés :
| /api/* (JSON), /sanctum/* (CSRF cookie Sanctum), /up (healthcheck), /pulse,
| /portal/* (assets buildés → 404 propre si absent), et — utile en test où
| les routes sont enregistrées sans contrainte de domaine — /auth/google/* et
| /calendar/* (flux iCal). Déclarée en DERNIER : les routes précédentes
| priment. Remplace l'ancienne route `/` welcome.
|
*/
$portalSpa = function (): void {
    Route::get('/{any?}', PortalSpaController::class)
        ->where('any', '^(?!api(?:/|$)|sanctum(?:/|$)|up$|pulse(?:/|$)|portal(?:/|$)|auth/google(?:/|$)|calendar(?:/|$)).*$')
        ->name('portal.spa');
};

if ($portalDomain = config('domains.portal')) {
    Route::domain($portalDomain)->group($portalSpa);
} else {
    $portalSpa();
}

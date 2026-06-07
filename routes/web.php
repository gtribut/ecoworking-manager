<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\CalendarFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

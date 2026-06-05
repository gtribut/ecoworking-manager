<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Auth admin — Google OAuth (C2.2, BRIEF §8 / ADR-0009)
|--------------------------------------------------------------------------
|
| Login Google additionnel réservé aux admins (match par email, domaine
| restreint, pas d'auto-provisioning). Contraint au sous-domaine admin en
| prod via config('domains.admin') ; sans contrainte en dev/test (localhost).
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

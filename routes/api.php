<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CurrentUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API portail (portail.ecoworking.fr/api/*) — ADR-0003 / ADR-0004
|--------------------------------------------------------------------------
|
| Authentification par session Sanctum mode SPA : la SPA appelle d'abord
| GET /sanctum/csrf-cookie, se connecte via POST /login (Fortify), puis toutes
| les requêtes /api/* portent le cookie de session. `auth:sanctum` valide.
|
| CLAUDE.md §3.1 : TOUTES les routes portail passent par `auth:sanctum`.
| Le domaine est contraint en prod via config('domains.portal') ; nul en
| dev/test → routes enregistrées sans contrainte de domaine (servies sur localhost).
|
*/

$register = function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', CurrentUserController::class)->name('api.user');
    });
};

if ($domain = config('domains.portal')) {
    Route::domain($domain)->group($register);
} else {
    $register();
}

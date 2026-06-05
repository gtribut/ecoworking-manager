<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProfileController;
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

        // C4.2 — Profil membre (lecture + édition partielle, auto-scopé).
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

        // C4.3 — Factures (liste scopée + téléchargement PDF).
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('api.invoices.pdf');
    });
};

if ($domain = config('domains.portal')) {
    Route::domain($domain)->group($register);
} else {
    $register();
}

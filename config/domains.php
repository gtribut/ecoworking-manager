<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sous-domaines admin / portail (ADR-0004)
    |--------------------------------------------------------------------------
    |
    | Une seule application Laravel sert deux surfaces sur deux sous-domaines :
    | `admin.ecoworking.fr` (Filament) et `portail.ecoworking.fr` (SPA + /api).
    |
    | En prod ces valeurs sont renseignées pour contraindre le routing par
    | domaine (Route::domain / $panel->domain). En dev/test elles restent nulles :
    | les routes sont alors enregistrées sans contrainte de domaine (l'app est
    | servie sur localhost), ce qui évite tout setup /etc/hosts pour les tests.
    |
    */

    'portal' => env('PORTAL_DOMAIN'),

    'admin' => env('ADMIN_DOMAIN'),

];

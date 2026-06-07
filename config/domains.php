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
    | En prod (`.fr`) et en dev local (`.test` via /etc/hosts) ces valeurs sont
    | renseignées pour contraindre le routing par domaine (Route::domain /
    | $panel->domain). En **test** (Pest) elles sont forcées nulles via
    | phpunit.xml : les routes sont alors enregistrées sans contrainte de domaine
    | (servies sur localhost), ce qui évite tout setup /etc/hosts pour les tests.
    |
    */

    'portal' => env('PORTAL_DOMAIN'),

    'admin' => env('ADMIN_DOMAIN'),

];

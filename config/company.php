<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identité légale de l'émetteur (Ecoworking)
|--------------------------------------------------------------------------
|
| Mentions obligatoires des factures (CGI art. 242 nonies A / art. 289).
| Valeurs alimentées par l'environnement (CLAUDE.md §3.3) — jamais hardcodées.
| Renseigner en prod via .env ; voir .env.example.
|
*/

return [
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Ecoworking'),
    'legal_form' => env('COMPANY_LEGAL_FORM', 'SCOP SARL'),
    'capital' => env('COMPANY_CAPITAL'),
    'siret' => env('COMPANY_SIRET'),
    'rcs' => env('COMPANY_RCS'),
    'vat_number' => env('COMPANY_VAT_NUMBER'),

    'address' => [
        'line1' => env('COMPANY_ADDRESS_LINE1'),
        'line2' => env('COMPANY_ADDRESS_LINE2'),
        'postal_code' => env('COMPANY_POSTAL_CODE'),
        'city' => env('COMPANY_CITY'),
        'country' => env('COMPANY_COUNTRY', 'France'),
    ],

    'email' => env('COMPANY_EMAIL'),
    'phone' => env('COMPANY_PHONE'),
];

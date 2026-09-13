<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google OAuth (Socialite) — login admin additionnel (C2.2, BRIEF §8).
    // `hosted_domain` restreint l'accès au Workspace d'Ecoworking (vérifié côté
    // callback, le paramètre `hd` envoyé à Google n'étant qu'indicatif).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN'),
    ],

    // Healthchecks.io (C10.2, BRIEF §16) : surveillance des crons. Une URL de
    // check par job planifié ; le scheduler ping en succès et /fail en échec.
    // Vide en dev → aucun ping (les helpers `pingOnSuccess` ignorent null).
    'healthchecks' => [
        'monthly_billing' => env('HEALTHCHECK_MONTHLY_BILLING_URL'),
        'overdue_invoices' => env('HEALTHCHECK_OVERDUE_INVOICES_URL'),
        'notifications_purge' => env('HEALTHCHECK_NOTIFICATIONS_PURGE_URL'),
    ],

    // Brevo (ex-Sendinblue) — email transactionnel en prod via driver API (C8).
    // Transport enregistré dans AppServiceProvider::boot() (Mail::extend), car
    // Laravel ne connaît pas nativement le scheme `brevo`. Vide en dev (Mailpit).
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

];

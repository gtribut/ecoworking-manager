<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Facturation récurrente (C6.5) : brouillons générés le 1er du mois (idempotent).
$monthlyBilling = Schedule::command('invoices:generate-monthly')->monthlyOn(1, '06:00');

// Bascule en retard des factures échues non soldées (C6.6) : tous les jours.
$overdueInvoices = Schedule::command('invoices:update-overdue')->dailyAt('07:00');

// Purge de l'historique des notifications in-app (lot G, PRD §3.8.4) :
// rétention configurable, passage quotidien hors heures de bureau.
$purgeNotifications = Schedule::command('notifications:purge')->dailyAt('04:00');

// Surveillance des crons via Healthchecks.io (C10.2) : ping en succès + /fail
// en échec. Activé seulement si l'URL de check est configurée (vide en dev).
if ($url = config('services.healthchecks.monthly_billing')) {
    $monthlyBilling->pingOnSuccess($url)->pingOnFailure($url.'/fail');
}

if ($url = config('services.healthchecks.overdue_invoices')) {
    $overdueInvoices->pingOnSuccess($url)->pingOnFailure($url.'/fail');
}

if ($url = config('services.healthchecks.notifications_purge')) {
    $purgeNotifications->pingOnSuccess($url)->pingOnFailure($url.'/fail');
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Facturation récurrente (C6.5) : brouillons générés le 1er du mois (idempotent).
Schedule::command('invoices:generate-monthly')->monthlyOn(1, '06:00');

// Bascule en retard des factures échues non soldées (C6.6) : tous les jours.
Schedule::command('invoices:update-overdue')->dailyAt('07:00');

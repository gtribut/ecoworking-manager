<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace l'envoi (unique) de la notification de retard (review F7). Le statut
 * `overdue` ne suffit plus comme marqueur : une facture échue peut être
 * recalculée en retard par l'observer paiements sans passer par le cron, et
 * un paiement partiel ne doit pas redéclencher la notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestampTz('overdue_notified_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('overdue_notified_at');
        });
    }
};

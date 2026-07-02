<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes manquants sur des FK fréquemment jointes (review 06 mineur 1,
 * convention data_model §1 « toutes les FK indexées ») : restitution de
 * ticket à l'annulation (bookings/desk_occupations) et navigation
 * facture ↔ avoir (self-FK invoices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('ticket_id');
        });

        Schema::table('desk_occupations', function (Blueprint $table) {
            $table->index('ticket_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('credit_note_for_invoice_id');
            $table->index('cancellation_credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['credit_note_for_invoice_id']);
            $table->dropIndex(['cancellation_credit_note_id']);
        });

        Schema::table('desk_occupations', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pose les clés étrangères différées : colonnes créées dans des phases
 * antérieures dont la table cible n'existait pas encore (dépendances
 * inter-phases / cycles tickets↔bookings, purchases↔invoices).
 * Voir la stratégie « FK différées » du data_model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->foreign('desk_id')->references('id')->on('resources')->nullOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('desk_occupation_id')->references('id')->on('desk_occupations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['desk_occupation_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropForeign(['desk_id']);
        });
    }
};

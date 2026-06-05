<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `consents` — historique APPEND-ONLY des consentements RGPD
 * (newsletter, annuaire, droit image, CGU…). data_model §4.1.
 * On ajoute une ligne à chaque changement, jamais d'écrasement (§6, append-only app).
 * `type` est un ensemble ouvert : pas de CHECK DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('granted');
            $table->timestampTz('granted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('source', 60)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};

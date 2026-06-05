<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `member_document_validations` — APPEND-ONLY : une ligne par validation
 * (qui/quand/version). Nouvelle version → nouvelle ligne, jamais d'écrasement
 * (§6.11, niveau applicatif). data_model §4.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20); // snapshot de la version validée
            $table->timestampTz('validated_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'internal_document_id']);
            $table->index('internal_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_document_validations');
    }
};

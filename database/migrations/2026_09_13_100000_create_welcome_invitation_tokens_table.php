<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons d'invitation d'accueil (PRD §3.2, lot F) — dépôt du broker
 * `welcome` (config/auth.php). Schéma identique à `password_reset_tokens`
 * (contrat de `DatabaseTokenRepository`), mais table SÉPARÉE pour que la
 * durée de validité de 3 jours ne déteigne jamais sur les jetons
 * « mot de passe oublié » (1 h).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welcome_invitation_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_invitation_tokens');
    }
};

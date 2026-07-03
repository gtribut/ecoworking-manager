<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons de connexion par magic link (C12.8a, PRD §3.2 / ADR-0011).
 *
 * Seul le hash SHA-256 du jeton est persisté (jamais le jeton en clair) :
 * une lecture de la table ne permet pas de forger un lien. Usage unique
 * (`used_at`) + expiration courte (`expires_at`, ~15 min), en complément de
 * la signature d'URL temporaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // Invalidation ciblée par utilisateur (changement de mot de passe).
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_link_tokens');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `users` — authentification de TOUS les comptes (admins, membres,
 * externes, contacts factu). data_model §4.1. Le profil public vit dans
 * `member_profiles`. Colonnes datées en timestamptz (convention §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');

            // 2FA Fortify (TOTP) — portail membre (optionnel). Colonnes définies
            // ici ; à l'installation de Fortify, ne PAS publier sa migration 2FA
            // (éviter le doublon).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();

            // 2FA Filament (TOTP natif) — back-office admin, obligatoire (C3.1,
            // ADR-0002 « auth séparée par contexte »). Mécanisme distinct de
            // Fortify : stockage chiffré via casts, jamais en fillable. Le membre
            // utilise Fortify ci-dessus ; l'admin Filament utilise ces colonnes.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();

            // Secret iCal révocable (flux perso + entité, PRD §3.5.8).
            $table->string('calendar_token', 64)->nullable()->unique();

            // Préférences notifications (PRD §3.8.4).
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->string('theme', 10)->nullable(); // light/dark/NULL(auto)

            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('anonymized_at')->nullable(); // marqueur RGPD §7

            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

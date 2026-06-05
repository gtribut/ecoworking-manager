<?php

declare(strict_types=1);

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `tickets` — tickets unitaires consommables. NON cessibles (user_id
 * immuable), AUCUNE expiration (pas de statut `expired`). data_model §4.2 / §6.9.
 *
 * `booking_id` / `desk_occupation_id` : colonnes ici, FK différées (Phase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status', 12)->default(TicketStatus::Available->value);
            $table->timestampTz('consumed_at')->nullable();

            // Cible de consommation — FK différées (bookings / desk_occupations en Phase 3)
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('desk_occupation_id')->nullable();

            $table->foreignId('credited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('credit_reason')->nullable();
            $table->timestampsTz();

            $table->index('purchase_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index(['user_id', 'type', 'status']); // compteurs « Mes tickets »
            $table->index('booking_id');
            $table->index('desk_occupation_id');
        });

        Check::enum('tickets', 'type', TicketType::values());
        Check::enum('tickets', 'status', TicketStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

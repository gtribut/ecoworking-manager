<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `bookings` — réservations de SALLES (meeting_room + event_room).
 * Pas les bureaux (→ desk_occupations). data_model §4.3 / §6.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();

            // Billable poly, NULL si interne/gratuit
            $table->string('billable_type', 20)->nullable();
            $table->unsignedBigInteger('billable_id')->nullable();

            $table->string('title')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 12)->default(BookingStatus::Confirmed->value);
            $table->decimal('price_ht', 10, 2)->nullable();  // snapshot si payant
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->uuid('recurrence_group_id')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('google_calendar_event_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('resource_id');
            $table->index('user_id');
            $table->index('status');
            $table->index(['resource_id', 'starts_at', 'ends_at']); // détection de conflit
            $table->index(['billable_type', 'billable_id']);
            $table->index('recurrence_group_id');
        });

        Check::enum('bookings', 'status', BookingStatus::values());
        Check::raw('bookings', 'bookings_ends_after_starts', 'ends_at > starts_at');

        // §6.8 — Anti-double-booking (backstop DB) : deux réservations `confirmed`
        // ne peuvent se chevaucher sur la même salle. Exclusion GiST sur l'intervalle
        // semi-ouvert [starts_at, ends_at) — l'adjacence (fin = début) est autorisée.
        // Requiert l'extension btree_gist (opérateur = sur resource_id en index GiST).
        DB::statement(
            "ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
             EXCLUDE USING gist (resource_id WITH =, tstzrange(starts_at, ends_at) WITH &&)
             WHERE (status = 'confirmed')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

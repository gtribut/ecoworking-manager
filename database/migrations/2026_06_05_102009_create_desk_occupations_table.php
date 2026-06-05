<?php

declare(strict_types=1);

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `desk_occupations` — occupation effective d'un bureau, par demi-journée.
 * Stocke surtout les `external_ticket` ; la présence par défaut des résidents/staff
 * est dérivée (assignment − absences), rarement matérialisée. data_model §4.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_occupations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_id')->constrained('resources')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('period', 10);
            $table->string('source', 20);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default(DeskOccupationStatus::Present->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('desk_id');
            $table->index('user_id');
            $table->index('date');
            $table->index(['date', 'source', 'status']); // calcul de dispo external
            $table->index(['desk_id', 'date', 'period']);
        });

        Check::enum('desk_occupations', 'period', Period::values());
        Check::enum('desk_occupations', 'source', DeskOccupationSource::values());
        Check::enum('desk_occupations', 'status', DeskOccupationStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_occupations');
    }
};

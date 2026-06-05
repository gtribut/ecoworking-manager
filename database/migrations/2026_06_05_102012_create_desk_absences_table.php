<?php

declare(strict_types=1);

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `desk_absences` — déclarations d'absence d'un résident/staff sur SON bureau.
 * Expansion des récurrences à la lecture (jamais de pré-génération). data_model §4.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end')->nullable(); // NULL = jour unique
            $table->string('period', 10)->default(Period::FullDay->value);
            $table->string('recurrence_type', 10)->default(DeskAbsenceRecurrence::None->value);
            $table->smallInteger('recurrence_day_of_week')->nullable(); // 0=dim … 6=sam
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('desk_id');
            $table->index('user_id');
            $table->index('date_start');
            $table->index('recurrence_type');
        });

        Check::enum('desk_absences', 'period', Period::values());
        Check::enum('desk_absences', 'recurrence_type', DeskAbsenceRecurrence::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_absences');
    }
};

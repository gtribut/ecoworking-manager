<?php

declare(strict_types=1);

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `resources` — bureaux (48) + salles de réunion (3) + salle event (1).
 * Champs adaptés au `type`. data_model §4.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('type', 15);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();
            $table->jsonb('features')->nullable();
            $table->string('assignment', 20)->nullable(); // desk only, NULL sinon
            $table->smallInteger('floor')->nullable();
            $table->string('svg_desk_id', 40)->nullable()->unique();
            $table->decimal('external_half_day_price_ht', 10, 2)->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->boolean('requires_admin')->default(false);
            $table->string('google_calendar_color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_out_of_service')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestampsTz();

            $table->index('type');
            $table->index('assignment');
            $table->index('floor');
            $table->index('is_active');
        });

        Check::enum('resources', 'type', ResourceType::values());
        Check::enum('resources', 'assignment', ResourceAssignment::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};

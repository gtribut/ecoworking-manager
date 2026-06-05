<?php

declare(strict_types=1);

use App\Enums\AnnouncementRegistrationStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `announcement_registrations` — inscriptions aux events. data_model §4.5.
 * Une inscription unique par (annonce, user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default(AnnouncementRegistrationStatus::Registered->value);
            $table->timestampTz('registered_at');
            $table->timestampsTz();

            $table->unique(['announcement_id', 'user_id']);
            $table->index('user_id');
        });

        Check::enum('announcement_registrations', 'status', AnnouncementRegistrationStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_registrations');
    }
};

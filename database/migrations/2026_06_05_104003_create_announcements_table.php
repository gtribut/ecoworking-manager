<?php

declare(strict_types=1);

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `announcements` — annonces (info/event/alert), inscription optionnelle
 * aux events. data_model §4.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('title');
            $table->text('body');
            $table->string('cover_image_path')->nullable();
            $table->timestampTz('event_starts_at')->nullable();
            $table->timestampTz('event_ends_at')->nullable();
            $table->string('location')->nullable();
            $table->integer('max_participants')->nullable();
            $table->boolean('requires_registration')->default(false);
            $table->string('visibility', 16)->default(Audience::All->value);
            $table->string('status', 12)->default(AnnouncementStatus::Draft->value);
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('type');
            $table->index('status');
            $table->index('published_at');
            $table->index('event_starts_at');
        });

        Check::enum('announcements', 'type', AnnouncementType::values());
        Check::enum('announcements', 'visibility', Audience::values());
        Check::enum('announcements', 'status', AnnouncementStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

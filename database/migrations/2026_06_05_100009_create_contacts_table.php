<?php

declare(strict_types=1);

use App\Enums\ContactRole;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `contacts` — personnes liées à une entité (facturation, management,
 * technique). Peut référencer un compte portail. data_model §4.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('role', 20);
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('company_id');
            $table->index('user_id');
            $table->index('role');
        });

        Check::enum('contacts', 'role', ContactRole::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

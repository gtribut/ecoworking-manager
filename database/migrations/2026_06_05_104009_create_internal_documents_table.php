<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Enums\InternalDocumentType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `internal_documents` — documents communs versionnés (charte, CGU, droit
 * image). Changement de version → re-validation requise. data_model §4.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('title');
            $table->string('version', 20);
            $table->text('body')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('audience', 16)->default(Audience::All->value);
            $table->timestampTz('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('type');
            $table->index('is_active');
        });

        Check::enum('internal_documents', 'type', InternalDocumentType::values());
        Check::enum('internal_documents', 'audience', Audience::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_documents');
    }
};

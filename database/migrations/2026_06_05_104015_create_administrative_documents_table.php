<?php

declare(strict_types=1);

use App\Enums\AdministrativeDocumentType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `administrative_documents` — documents propres à une entité (contrats,
 * avenants, contrat de domiciliation). Rétention 10 ans. data_model §4.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('title');
            $table->string('pdf_path');
            $table->date('document_date')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('company_id');
            $table->index('type');
        });

        Check::enum('administrative_documents', 'type', AdministrativeDocumentType::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_documents');
    }
};

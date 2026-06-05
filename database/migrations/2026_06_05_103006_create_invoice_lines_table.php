<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoice_lines` — lignes figées à l'émission (jamais recalculées, §6.7).
 * `related` polymorphe : origine de la ligne (subscription/purchase/booking/NULL).
 * data_model §4.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Origine poly (subscription/purchase/booking/NULL)
            $table->string('related_type', 20)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price_ht', 10, 2);          // figé
            $table->decimal('discount_rate', 5, 2)->nullable(); // snapshot remise entité
            $table->decimal('vat_rate', 5, 2)->default(20.00);  // figé
            $table->decimal('line_total_ht', 10, 2);            // figé (après remise)
            $table->decimal('line_vat', 10, 2);                 // figé
            $table->decimal('line_total_ttc', 10, 2);           // figé
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestampsTz();

            $table->index('invoice_id');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};

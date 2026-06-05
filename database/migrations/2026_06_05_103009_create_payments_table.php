<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `payments` — encaissements (statuts manuels, pas de paiement en ligne MVP).
 * FK invoices en `restrict` (intégrité comptable). data_model §4.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->string('method', 10);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('invoice_id');
            $table->index('method');
            $table->index('paid_at');
            $table->index('deleted_at');
        });

        Check::enum('payments', 'method', PaymentMethod::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

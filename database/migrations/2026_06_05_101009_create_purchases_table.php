<?php

declare(strict_types=1);

use App\Enums\TicketType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `purchases` — achats ponctuels (tickets/packs). Crédités par l'admin (MVP).
 * Prix SNAPSHOTÉ à l'achat (contrairement aux subscriptions). data_model §4.2.
 *
 * `invoice_id` : colonne ici, FK vers `invoices` différée (Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Billable poly (entité facturée), NULL si non facturé
            $table->string('billable_type', 20)->nullable();
            $table->unsignedBigInteger('billable_id')->nullable();

            // Facture d'origine (crédit auto) — FK différée vers invoices
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->string('ticket_type', 30);
            $table->integer('quantity');
            $table->decimal('unit_price_ht', 10, 2)->default(0); // snapshot
            $table->decimal('vat_rate', 5, 2)->default(20.00);   // snapshot
            $table->string('label')->nullable();
            $table->timestampTz('purchased_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('offer_id');
            $table->index('user_id');
            $table->index('invoice_id');
            $table->index('ticket_type');
            $table->index(['billable_type', 'billable_id']);
        });

        Check::enum('purchases', 'ticket_type', TicketType::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

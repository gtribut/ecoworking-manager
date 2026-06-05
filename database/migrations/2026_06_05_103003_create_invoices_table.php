<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoices` — factures. Émise = JAMAIS supprimée (CGI art. 289) :
 * seuls les `draft` sont supprimables (InvoicePolicy). Annulation = `cancelled`
 * + avoir. Montants figés sur les lignes à l'émission. data_model §4.4 / §6.
 *
 * `number` NULL tant que `draft` (le compteur n'est consommé qu'à l'émission, §6.5).
 * Champs Factur-X (V2) présents dès le MVP, nullables (§8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();

            // Billable poly (cible de la facture)
            $table->string('billable_type', 20);
            $table->unsignedBigInteger('billable_id');

            $table->string('status', 16)->default(InvoiceStatus::Draft->value);
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable(); // issued_at + 14 j

            // Snapshot adresse de facturation (figé à l'émission)
            $table->string('billing_name')->nullable();
            $table->jsonb('billing_address')->nullable();
            $table->string('billing_siret', 14)->nullable();
            $table->string('billing_vat_number', 20)->nullable();

            // Totaux figés
            $table->decimal('subtotal_ht', 10, 2)->default(0);
            $table->decimal('total_vat', 10, 2)->default(0);
            $table->decimal('total_ttc', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);

            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();

            // Avoir / annulation
            $table->boolean('is_credit_note')->default(false);
            $table->foreignId('credit_note_for_invoice_id')->nullable();
            $table->foreignId('cancellation_credit_note_id')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            // Facturation électronique (V2, §8)
            $table->string('factur_x_xml_path')->nullable();
            $table->string('pa_transmission_id', 120)->nullable();
            $table->string('pa_transmission_status', 40)->nullable();

            $table->foreignId('emitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['billable_type', 'billable_id']);
            $table->index('status');
            $table->index('issued_at');
            $table->index('due_at');
            $table->index('deleted_at');

            // Self-FK (avoir ↔ facture annulée) — restrict (intégrité comptable)
            $table->foreign('credit_note_for_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('cancellation_credit_note_id')->references('id')->on('invoices')->restrictOnDelete();
        });

        Check::enum('invoices', 'status', InvoiceStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

<?php

declare(strict_types=1);

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\PaymentMethod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `companies` — entités billables : entreprises (`company`, SIRET) et
 * particuliers (`individual`). Porte la remise négociée et le mandat SEPA.
 * data_model §4.1. RGPD : jamais l'IBAN complet (CLAUDE.md §3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 10);
            $table->string('status', 10)->default(CompanyStatus::Active->value);

            // Identité morale (requis si `company`, validé côté app)
            $table->string('legal_name')->nullable();
            $table->string('legal_form', 50)->nullable();
            $table->string('siret', 14)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('ape_code', 10)->nullable();

            // Identité particulier (requis si `individual`, validé côté app)
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->date('birth_date')->nullable();

            // Facturation / adresse
            $table->string('billing_email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 2)->default('FR');

            // SEPA — jamais l'IBAN complet (RGPD)
            $table->string('preferred_payment_method', 10)->nullable();
            $table->string('sepa_iban_last4', 4)->nullable();
            $table->string('sepa_mandate_reference', 64)->nullable();
            $table->date('sepa_mandate_signed_at')->nullable();
            $table->string('sepa_mandate_path')->nullable();

            // Remise négociée (unique mécanisme de remise, PRD §6.4)
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->string('discount_scope', 40)->nullable();
            $table->string('discount_note')->nullable();

            $table->text('admin_notes')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('entity_type');
            $table->index('status');
            $table->index('siret');
            $table->index('city');
            $table->index('deleted_at');
        });

        Check::enum('companies', 'entity_type', CompanyType::values());
        Check::enum('companies', 'status', CompanyStatus::values());
        Check::enum('companies', 'preferred_payment_method', PaymentMethod::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

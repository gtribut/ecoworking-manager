<?php

declare(strict_types=1);

use App\Enums\BillingPeriod;
use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use App\Enums\TicketType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `offers` — catalogue des prestations. data_model §4.2.
 * Les prix ne sont PAS figés chez l'abonné : relus depuis l'offre courante
 * à chaque facturation (§6.7). Packs = SKU distincts (un prix par offre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('subscriber_kind', 10)->default(SubscriberKind::Member->value);
            $table->string('billing_period', 10)->nullable();
            $table->decimal('unit_price_ht', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->integer('quantity_per_purchase')->default(1);
            $table->string('ticket_type', 30)->nullable();
            $table->integer('max_per_user')->nullable();
            $table->boolean('requires_active_resident')->default(false);
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestampsTz();

            $table->index('type');
            $table->index('is_active');
            $table->index('ticket_type');
        });

        Check::enum('offers', 'type', OfferType::values());
        Check::enum('offers', 'subscriber_kind', SubscriberKind::values());
        Check::enum('offers', 'billing_period', BillingPeriod::values());
        Check::enum('offers', 'ticket_type', TicketType::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};

<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `subscriptions` — abonnements récurrents (membres + domiciliation entité).
 * Souscripteur ET billable polymorphes. Aucun prix figé (relu du catalogue). §4.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();

            // Souscripteur poly (user = abo membre ; company = domiciliation)
            $table->string('subscriber_type', 20);
            $table->unsignedBigInteger('subscriber_id');

            // Billable poly (entité ou user facturé)
            $table->string('billable_type', 20);
            $table->unsignedBigInteger('billable_id');

            $table->string('status', 12)->default(SubscriptionStatus::Active->value);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->smallInteger('billing_day')->default(1);
            $table->timestampTz('paused_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('offer_id');
            $table->index(['subscriber_type', 'subscriber_id']);
            $table->index(['billable_type', 'billable_id']);
            $table->index('status');
        });

        Check::enum('subscriptions', 'status', SubscriptionStatus::values());

        // §6.3 — Domiciliation unique par entité : au plus un abonnement
        // « company » actif par souscripteur. subscriber_type='company' ⟺
        // domiciliation (seule offre subscriber_kind=entity), donc l'index n'a
        // pas besoin de référencer l'offre. Backstop DB du check applicatif.
        DB::statement(
            "CREATE UNIQUE INDEX subscriptions_active_domiciliation_unique
             ON subscriptions (subscriber_id)
             WHERE subscriber_type = 'company' AND status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

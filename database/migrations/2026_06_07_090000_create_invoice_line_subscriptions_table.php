<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoice_line_subscriptions` — liaison ligne de facture regroupée ↔
 * abonnements couverts (C6.5, PRD §5.1 « Regroupement par entité »).
 *
 * Une facture récurrente est émise PAR ENTITÉ ; chaque ligne regroupe les
 * abonnements d'un même type (× quantité). Cette table garde la traçabilité
 * fine (quel abonnement, quelle période, quelle quote-part HT) ET porte le
 * backstop DB d'idempotence : UNIQUE (subscription_id, period_start, period_end)
 * → un abonnement n'est facturé qu'une fois par période. data_model §4.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount_ht', 10, 2); // quote-part HT (remise + prorata inclus), figée à l'émission
            $table->timestampsTz();

            $table->index('invoice_line_id');
            $table->index('subscription_id');
            // Backstop d'idempotence au grain abonnement (§5.1).
            $table->unique(['subscription_id', 'period_start', 'period_end'], 'uniq_sub_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_subscriptions');
    }
};

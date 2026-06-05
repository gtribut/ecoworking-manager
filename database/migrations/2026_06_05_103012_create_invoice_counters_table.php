<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoice_counters` — compteur de numérotation chronologique sans trou,
 * verrouillé en transaction (SELECT … FOR UPDATE). Un compteur par année.
 * data_model §4.4 / §6.5. Format émis : EW-YYYY-NNNNN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unique();
            $table->integer('value')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_counters');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Active l'extension PostgreSQL `btree_gist`, requise par la contrainte
 * d'exclusion GiST anti-double-booking sur `bookings` (data_model §6.8).
 *
 * Sur Clever Cloud (PG managé), vérifier au provisioning que `btree_gist`
 * fait partie des extensions autorisées (cf. ADR-0008).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
    }
};

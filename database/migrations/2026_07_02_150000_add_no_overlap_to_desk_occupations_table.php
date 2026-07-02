<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backstop DB anti-double-occupation des bureaux nomades (review backend M3).
 * Le `lockForUpdate()->exists()` applicatif ne verrouille rien quand aucune
 * ligne n'existe : deux transactions concurrentes pouvaient créer deux
 * occupations `present` du même bureau. Symétrique du `bookings_no_overlap`
 * des salles (data_model §6.8), au grain (bureau, date, demi-journée) — la
 * journée complète chevauche les deux demi-journées via un int4range :
 * morning = [0,1), afternoon = [1,2), full_day = [0,2).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE desk_occupations ADD CONSTRAINT desk_occupations_no_overlap
             EXCLUDE USING gist (
                 desk_id WITH =,
                 date WITH =,
                 (CASE period
                     WHEN 'morning' THEN int4range(0, 1)
                     WHEN 'afternoon' THEN int4range(1, 2)
                     ELSE int4range(0, 2)
                 END) WITH &&
             )
             WHERE (status = 'present')"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE desk_occupations DROP CONSTRAINT IF EXISTS desk_occupations_no_overlap');
    }
};

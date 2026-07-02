<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceCounter;
use Illuminate\Database\DatabaseManager;

/**
 * Numérotation chronologique sans trou (CGI art. 289 ; CLAUDE.md §3.6 / §6.2).
 * Le numéro n'est consommé qu'à l'émission définitive — jamais par un brouillon.
 * Verrou pessimiste `lockForUpdate` dans une transaction : deux émissions
 * concurrentes ne peuvent pas obtenir le même numéro.
 */
final class InvoiceNumberingService
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Réserve et retourne le prochain numéro pour l'année en cours.
     * Format : `EW-YYYY-NNNNN`.
     */
    public function nextNumber(int $year): string
    {
        return $this->db->transaction(function () use ($year): string {
            // `firstOrCreate` sous verrou ne protège pas la PREMIÈRE émission de
            // l'année : aucune ligne à verrouiller, deux transactions concurrentes
            // tenteraient toutes deux l'INSERT (violation UNIQUE pour l'une).
            // L'upsert « do nothing » rend la création idempotente, puis le
            // SELECT ... FOR UPDATE sérialise les incréments.
            InvoiceCounter::query()->insertOrIgnore([
                'year' => $year,
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = InvoiceCounter::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->increment('value');

            return sprintf('EW-%d-%05d', $year, $counter->value);
        });
    }
}

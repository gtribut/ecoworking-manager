<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jours fériés français (métropole) — utilisés pour restreindre les
 * réservations external aux jours ouvrés (PRD §6.1). Calcul local, sans
 * dépendance tierce : fêtes fixes + fêtes mobiles dérivées de Pâques
 * (algorithme de Butcher/Meeus, valable pour le calendrier grégorien).
 */
final class FrenchHolidays
{
    /** Cache des dates (Y-m-d) par année, évite de recalculer Pâques. */
    private static array $cache = [];

    /** Jour ouvré = lundi→vendredi ET hors jour férié. */
    public static function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! self::isHoliday($date);
    }

    public static function isHoliday(CarbonInterface $date): bool
    {
        return in_array($date->format('Y-m-d'), self::forYear((int) $date->year), true);
    }

    /**
     * @return list<string> dates Y-m-d des jours fériés de l'année
     */
    public static function forYear(int $year): array
    {
        if (isset(self::$cache[$year])) {
            return self::$cache[$year];
        }

        $easter = self::easter($year);

        $dates = [
            sprintf('%d-01-01', $year),                  // Jour de l'an
            sprintf('%d-05-01', $year),                  // Fête du travail
            sprintf('%d-05-08', $year),                  // Victoire 1945
            sprintf('%d-07-14', $year),                  // Fête nationale
            sprintf('%d-08-15', $year),                  // Assomption
            sprintf('%d-11-01', $year),                  // Toussaint
            sprintf('%d-11-11', $year),                  // Armistice
            sprintf('%d-12-25', $year),                  // Noël
            $easter->addDays(1)->format('Y-m-d'),        // Lundi de Pâques
            $easter->addDays(39)->format('Y-m-d'),       // Ascension
            $easter->addDays(50)->format('Y-m-d'),       // Lundi de Pentecôte
        ];

        sort($dates);

        return self::$cache[$year] = $dates;
    }

    /** Dimanche de Pâques (algorithme de Meeus/Butcher, grégorien). */
    private static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0);
    }
}

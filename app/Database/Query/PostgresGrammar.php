<?php

declare(strict_types=1);

namespace App\Database\Query;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BaseGrammar;

/**
 * Grammar Postgres dont le format de date porte le **décalage horaire**
 * (ADR-0012). Corrige un décalage systématique de +2 h à l'écriture.
 *
 * Le format par défaut de Laravel est `Y-m-d H:i:s`, sans décalage. L'app
 * tournant en `Europe/Paris` (ADR-0010) et la session Postgres en UTC, deux
 * `Carbon` désignant le MÊME instant produisaient deux chaînes différentes
 * (`14:00:00` en Paris, `12:00:00` en UTC) que Postgres interprétait toutes
 * deux comme de l'UTC. Résultat : les écritures partant d'un `Carbon` en heure
 * de Paris (`now()`, `halfDayBounds()`, seeders, back-office Filament) étaient
 * stockées 2 h trop tard, tandis que celles de la SPA (ISO UTC) étaient justes.
 *
 * En ajoutant le décalage, les deux chaînes désignent le même instant et
 * Postgres n'a plus à deviner : le fuseau de session devient sans effet.
 *
 * Ce point d'accroche couvre les DEUX chemins, ce qu'un `$dateFormat` posé sur
 * les modèles ne ferait pas :
 * - écritures, via `Model::getDateFormat()` qui retombe sur le grammar ;
 * - comparaisons `where`, via `Connection::prepareBindings()`.
 *
 * Colonnes `date` (facturation : `issued_at`, `due_at`, `paid_at`, périodes) :
 * aucun risque de glissement d'un jour, Postgres ignore le décalage en castant
 * vers `date` — verrouillé par un test.
 */
final class PostgresGrammar extends BaseGrammar
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }
}

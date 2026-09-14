<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fuseau horaire : cohérence des écritures `timestamptz` (décision 14/09).
 *
 * L'app tourne en `Europe/Paris` (ADR-0010) et la session Postgres en UTC.
 * Eloquent sérialisait les `Carbon` au format `Y-m-d H:i:s`, **sans décalage** :
 * deux instants identiques exprimés dans deux fuseaux produisaient deux chaînes
 * différentes, que Postgres interprétait toutes deux comme de l'UTC.
 *
 * Conséquence : toute écriture partant d'un `Carbon` en heure de Paris
 * (`now()`, `halfDayBounds()` des demi-journées external, seeders, back-office
 * Filament) était stockée **+2 h**, alors qu'une écriture partant d'un `Carbon`
 * en UTC (ce qu'envoie la SPA via `toISOString()`) était correcte.
 *
 * Le correctif ajoute le décalage au format de date du query grammar, ce qui
 * couvre à la fois les écritures (`Model::fromDateTime()`) et les comparaisons
 * en `where` (`Connection::prepareBindings()`). Attention : poser
 * `'timezone' => 'Europe/Paris'` sur la connexion **ne corrigerait pas** ce
 * bug, cela ne ferait qu'en inverser le sens (cf. ADR-0012).
 */
it('stocke le même instant quel que soit le fuseau du Carbon écrit', function () {
    // Deux ressources distinctes : une fois le bug corrigé les deux résas visent
    // le MÊME créneau, et la contrainte d'exclusion `bookings_no_overlap` les
    // refuserait sur une ressource unique.
    $first = Resource::factory()->create();
    $second = Resource::factory()->create();
    $user = User::factory()->create();

    // Le MÊME instant, exprimé dans deux fuseaux différents.
    $paris = Carbon::parse('2026-10-01 14:00:00', 'Europe/Paris');
    $utc = Carbon::parse('2026-10-01T12:00:00Z');

    expect($paris->equalTo($utc))->toBeTrue('les deux Carbon doivent désigner le même instant');

    $fromPhp = Booking::create([
        'resource_id' => $first->id,
        'user_id' => $user->id,
        'starts_at' => $paris->copy(),
        'ends_at' => $paris->copy()->addHour(),
        'status' => 'confirmed',
    ]);

    $fromSpa = Booking::create([
        'resource_id' => $second->id,
        'user_id' => $user->id,
        'starts_at' => $utc->copy(),
        'ends_at' => $utc->copy()->addHour(),
        'status' => 'confirmed',
    ]);

    // Comparaison côté SQL : c'est l'instant réellement stocké qui compte,
    // pas sa représentation relue en PHP.
    $stored = DB::table('bookings')
        ->whereIn('id', [$fromPhp->id, $fromSpa->id])
        ->selectRaw('COUNT(DISTINCT starts_at) AS distinct_instants')
        ->value('distinct_instants');

    expect((int) $stored)->toBe(1, 'les deux écritures doivent viser le même instant en base');

    expect($fromPhp->fresh()->starts_at->equalTo($utc))->toBeTrue();
    expect($fromSpa->fresh()->starts_at->equalTo($utc))->toBeTrue();
});

it('compare correctement un Carbon en heure de Paris dans un where', function () {
    $resource = Resource::factory()->create();
    $user = User::factory()->create();

    Booking::create([
        'resource_id' => $resource->id,
        'user_id' => $user->id,
        'starts_at' => Carbon::parse('2026-10-01T12:00:00Z'),
        'ends_at' => Carbon::parse('2026-10-01T13:00:00Z'),
        'status' => 'confirmed',
    ]);

    // 13 h à Paris = 11 h UTC, soit AVANT la résa : elle doit être trouvée.
    $bornes = Carbon::parse('2026-10-01 13:00:00', 'Europe/Paris');

    expect(Booking::where('starts_at', '>', $bornes)->count())
        ->toBe(1, 'un Carbon Paris utilisé en where doit viser le bon instant');
});

/**
 * Les colonnes `date` (facturation : issued_at, due_at, paid_at, périodes)
 * ne doivent PAS glisser d'un jour : Postgres ignore le décalage en castant
 * vers `date`, mais autant le verrouiller par un test.
 */
it('ne décale pas les colonnes date de facturation', function () {
    $invoice = Invoice::factory()->create([
        // Minuit à Paris = 22 h UTC la veille : le cas limite.
        'issued_at' => Carbon::parse('2026-10-01 00:00:00', 'Europe/Paris'),
    ]);

    expect(DB::table('invoices')->where('id', $invoice->id)->value('issued_at'))
        ->toStartWith('2026-10-01');
});

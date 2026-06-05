<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Tests de schéma (niveau DB) — Phase 3 « Ressources & occupation » (data_model §4.3 / §6).
 */

function resourceRow(array $overrides = []): array
{
    return array_merge([
        'type' => 'meeting_room',
        'name' => 'Salle test',
        'requires_admin' => false,
        'is_active' => true,
        'is_out_of_service' => false,
        'display_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function bookingRow(array $overrides = []): array
{
    return array_merge([
        'status' => 'confirmed',
        'is_internal' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('rejette un resources.type hors énumération (CHECK)', function () {
    expect(fn () => DB::table('resources')->insert(resourceRow(['type' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('rejette une réservation dont la fin précède le début (CHECK)', function () {
    $r = DB::table('resources')->insertGetId(resourceRow());

    expect(fn () => DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r,
        'starts_at' => '2026-07-01 12:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ])))->toThrow(QueryException::class);
});

it('refuse deux réservations confirmées qui se chevauchent (exclusion GiST §6.8)', function () {
    $r = DB::table('resources')->insertGetId(resourceRow());

    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));

    expect(fn () => DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'starts_at' => '2026-07-01 11:00:00', 'ends_at' => '2026-07-01 13:00:00',
    ])))->toThrow(QueryException::class);
});

it('autorise deux réservations adjacentes (fin = début, intervalle semi-ouvert)', function () {
    $r = DB::table('resources')->insertGetId(resourceRow());

    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));
    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'starts_at' => '2026-07-01 12:00:00', 'ends_at' => '2026-07-01 13:00:00',
    ]));

    expect(DB::table('bookings')->where('resource_id', $r)->count())->toBe(2);
});

it('ignore les réservations annulées dans la détection de chevauchement', function () {
    $r = DB::table('resources')->insertGetId(resourceRow());

    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'status' => 'cancelled',
        'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));
    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r, 'status' => 'confirmed',
        'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));

    expect(DB::table('bookings')->where('resource_id', $r)->count())->toBe(2);
});

it('autorise le même créneau sur deux salles distinctes', function () {
    $r1 = DB::table('resources')->insertGetId(resourceRow());
    $r2 = DB::table('resources')->insertGetId(resourceRow());

    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r1, 'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));
    DB::table('bookings')->insert(bookingRow([
        'resource_id' => $r2, 'starts_at' => '2026-07-01 10:00:00', 'ends_at' => '2026-07-01 12:00:00',
    ]));

    expect(DB::table('bookings')->where('status', 'confirmed')->count())->toBe(2);
});

it('rejette un desk_occupations.source hors énumération (CHECK)', function () {
    $deskId = DB::table('resources')->insertGetId(resourceRow(['type' => 'desk', 'assignment' => 'unassigned']));
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'O', 'last_name' => 'O', 'email' => 'occ_'.uniqid().'@ecoworking.fr',
        'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('desk_occupations')->insert([
        'desk_id' => $deskId, 'user_id' => $userId, 'date' => '2026-07-01',
        'period' => 'morning', 'source' => 'bogus', 'status' => 'present',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Tests de schéma (niveau DB) — Phase 2 « Catalogue & contrats » (data_model §4.2 / §6).
 */

function catalogOfferRow(array $overrides = []): array
{
    return array_merge([
        'code' => 'offer_'.uniqid(),
        'name' => 'Offre test',
        'type' => 'subscription',
        'subscriber_kind' => 'member',
        'unit_price_ht' => 10.00,
        'vat_rate' => 20.00,
        'quantity_per_purchase' => 1,
        'requires_active_resident' => false,
        'is_active' => true,
        'is_public' => true,
        'display_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function catalogSubRow(array $overrides = []): array
{
    return array_merge([
        'subscriber_type' => 'company',
        'subscriber_id' => 1,
        'billable_type' => 'company',
        'billable_id' => 1,
        'status' => 'active',
        'starts_at' => now()->toDateString(),
        'billing_day' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('impose un code offre unique', function () {
    DB::table('offers')->insert(catalogOfferRow(['code' => 'resident_desk']));

    expect(fn () => DB::table('offers')->insert(catalogOfferRow(['code' => 'resident_desk'])))
        ->toThrow(QueryException::class);
});

it('rejette un offers.type hors énumération (CHECK)', function () {
    expect(fn () => DB::table('offers')->insert(catalogOfferRow(['type' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('rejette un tickets.status hors énumération — pas d\'expired (§4.2)', function () {
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'T', 'last_name' => 'T', 'email' => 'tk_'.uniqid().'@ecoworking.fr',
        'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('tickets')->insert([
        'user_id' => $userId, 'type' => 'desk_half_day', 'status' => 'expired',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('impose une seule domiciliation active par entité (index partiel §6.3)', function () {
    $offerId = DB::table('offers')->insertGetId(
        catalogOfferRow(['code' => 'domiciliation', 'subscriber_kind' => 'entity'])
    );

    DB::table('subscriptions')->insert(catalogSubRow([
        'offer_id' => $offerId, 'subscriber_id' => 42, 'billable_id' => 42, 'status' => 'active',
    ]));

    expect(fn () => DB::table('subscriptions')->insert(catalogSubRow([
        'offer_id' => $offerId, 'subscriber_id' => 42, 'billable_id' => 42, 'status' => 'active',
    ])))->toThrow(QueryException::class);
});

it('autorise une domiciliation active + une annulée pour la même entité', function () {
    $offerId = DB::table('offers')->insertGetId(
        catalogOfferRow(['code' => 'domiciliation', 'subscriber_kind' => 'entity'])
    );

    DB::table('subscriptions')->insert(catalogSubRow([
        'offer_id' => $offerId, 'subscriber_id' => 7, 'billable_id' => 7, 'status' => 'cancelled',
    ]));
    DB::table('subscriptions')->insert(catalogSubRow([
        'offer_id' => $offerId, 'subscriber_id' => 7, 'billable_id' => 7, 'status' => 'active',
    ]));

    expect(DB::table('subscriptions')->where('subscriber_id', 7)->count())->toBe(2);
});

it('autorise une domiciliation active pour deux entités distinctes', function () {
    $offerId = DB::table('offers')->insertGetId(
        catalogOfferRow(['code' => 'domiciliation', 'subscriber_kind' => 'entity'])
    );

    DB::table('subscriptions')->insert(catalogSubRow(['offer_id' => $offerId, 'subscriber_id' => 1, 'billable_id' => 1]));
    DB::table('subscriptions')->insert(catalogSubRow(['offer_id' => $offerId, 'subscriber_id' => 2, 'billable_id' => 2]));

    expect(DB::table('subscriptions')->where('status', 'active')->count())->toBe(2);
});

it('accepte une chaîne catalogue valide (offer → subscription user + purchase → tickets)', function () {
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'T', 'last_name' => 'T', 'email' => 'cat_'.uniqid().'@ecoworking.fr',
        'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $offerId = DB::table('offers')->insertGetId(catalogOfferRow(['code' => 'desk_half_day_pack2', 'type' => 'pack', 'ticket_type' => 'desk_half_day', 'quantity_per_purchase' => 2]));

    // Abonnement membre (subscriber=user) — hors index domiciliation
    DB::table('subscriptions')->insert(catalogSubRow([
        'offer_id' => $offerId, 'subscriber_type' => 'user', 'subscriber_id' => $userId,
        'billable_type' => 'user', 'billable_id' => $userId,
    ]));

    $purchaseId = DB::table('purchases')->insertGetId([
        'offer_id' => $offerId, 'user_id' => $userId, 'ticket_type' => 'desk_half_day',
        'quantity' => 2, 'unit_price_ht' => 31.50, 'vat_rate' => 20.00,
        'purchased_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach (range(1, 2) as $i) {
        DB::table('tickets')->insert([
            'purchase_id' => $purchaseId, 'user_id' => $userId, 'type' => 'desk_half_day',
            'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    expect(DB::table('tickets')->where('purchase_id', $purchaseId)->count())->toBe(2)
        ->and(DB::table('subscriptions')->where('subscriber_type', 'user')->where('subscriber_id', $userId)->exists())->toBeTrue();
});

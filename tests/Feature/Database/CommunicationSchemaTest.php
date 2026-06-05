<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Tests de schéma (niveau DB) — Phase 5 « Communication & documents » (data_model §4.5)
 * + vérification des clés étrangères différées (add_deferred_foreign_keys).
 */

function communicationUserId(): int
{
    return DB::table('users')->insertGetId([
        'first_name' => 'C', 'last_name' => 'C', 'email' => 'comm_'.uniqid().'@ecoworking.fr',
        'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function announcementRow(array $overrides = []): array
{
    return array_merge([
        'type' => 'info',
        'title' => 'Annonce test',
        'body' => 'Corps markdown',
        'requires_registration' => false,
        'visibility' => 'all',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('rejette un announcements.type hors énumération (CHECK)', function () {
    expect(fn () => DB::table('announcements')->insert(announcementRow(['type' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('impose une inscription unique par (annonce, user)', function () {
    $announcementId = DB::table('announcements')->insertGetId(announcementRow(['type' => 'event', 'requires_registration' => true]));
    $userId = communicationUserId();

    DB::table('announcement_registrations')->insert([
        'announcement_id' => $announcementId, 'user_id' => $userId, 'status' => 'registered',
        'registered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('announcement_registrations')->insert([
        'announcement_id' => $announcementId, 'user_id' => $userId, 'status' => 'registered',
        'registered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('valide un document interne et le supprime en cascade', function () {
    $userId = communicationUserId();
    $docId = DB::table('internal_documents')->insertGetId([
        'type' => 'charter', 'title' => 'Charte', 'version' => 'v1.0', 'audience' => 'all',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('member_document_validations')->insert([
        'internal_document_id' => $docId, 'user_id' => $userId, 'version' => 'v1.0',
        'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('internal_documents')->where('id', $docId)->delete();

    expect(DB::table('member_document_validations')->where('internal_document_id', $docId)->count())->toBe(0);
});

it('rejette un administrative_documents.type hors énumération (CHECK)', function () {
    $companyId = DB::table('companies')->insertGetId([
        'entity_type' => 'company', 'status' => 'active', 'legal_name' => 'Acme', 'country' => 'FR',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('administrative_documents')->insert([
        'company_id' => $companyId, 'type' => 'bogus', 'title' => 'Doc', 'pdf_path' => 'x.pdf',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('empêche la suppression d\'une entité ayant un document administratif (restrict)', function () {
    $companyId = DB::table('companies')->insertGetId([
        'entity_type' => 'company', 'status' => 'active', 'legal_name' => 'Acme', 'country' => 'FR',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('administrative_documents')->insert([
        'company_id' => $companyId, 'type' => 'domiciliation', 'title' => 'Contrat', 'pdf_path' => 'x.pdf',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('companies')->where('id', $companyId)->delete())
        ->toThrow(QueryException::class);
});

it('applique les FK différées purchases.invoice_id / tickets.booking_id / desk_occupation_id', function () {
    $userId = communicationUserId();

    // purchases.invoice_id → invoices
    expect(fn () => DB::table('purchases')->insert([
        'user_id' => $userId, 'invoice_id' => 999999, 'ticket_type' => 'desk_half_day',
        'quantity' => 1, 'unit_price_ht' => 0, 'vat_rate' => 20, 'purchased_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // tickets.booking_id → bookings
    expect(fn () => DB::table('tickets')->insert([
        'user_id' => $userId, 'type' => 'meeting_room_half_day', 'status' => 'used',
        'booking_id' => 999999, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // tickets.desk_occupation_id → desk_occupations
    expect(fn () => DB::table('tickets')->insert([
        'user_id' => $userId, 'type' => 'desk_half_day', 'status' => 'used',
        'desk_occupation_id' => 999999, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

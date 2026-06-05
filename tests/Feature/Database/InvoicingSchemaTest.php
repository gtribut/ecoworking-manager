<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Tests de schéma (niveau DB) — Phase 4 « Facturation » (data_model §4.4 / §6).
 */

function invoiceRow(array $overrides = []): array
{
    return array_merge([
        'billable_type' => 'company',
        'billable_id' => 1,
        'status' => 'draft',
        'subtotal_ht' => 0,
        'total_vat' => 0,
        'total_ttc' => 0,
        'amount_paid' => 0,
        'is_credit_note' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function invoiceLineRow(array $overrides = []): array
{
    return array_merge([
        'description' => 'Abonnement bureau résident',
        'quantity' => 1,
        'unit_price_ht' => 328.50,
        'vat_rate' => 20.00,
        'line_total_ht' => 328.50,
        'line_vat' => 65.70,
        'line_total_ttc' => 394.20,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('impose un number de facture unique', function () {
    DB::table('invoices')->insert(invoiceRow(['number' => 'EW-2026-00001', 'status' => 'sent', 'issued_at' => '2026-06-01']));

    expect(fn () => DB::table('invoices')->insert(invoiceRow(['number' => 'EW-2026-00001', 'status' => 'sent'])))
        ->toThrow(QueryException::class);
});

it('tolère plusieurs brouillons sans numéro (number NULL)', function () {
    DB::table('invoices')->insert(invoiceRow());
    DB::table('invoices')->insert(invoiceRow());

    expect(DB::table('invoices')->whereNull('number')->count())->toBe(2);
});

it('rejette un invoices.status hors énumération (CHECK)', function () {
    expect(fn () => DB::table('invoices')->insert(invoiceRow(['status' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('impose une année unique dans invoice_counters (§6.5)', function () {
    DB::table('invoice_counters')->insert(['year' => 2026, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('invoice_counters')->insert(['year' => 2026, 'value' => 5, 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('permet de lier un avoir à sa facture annulée (self-FK)', function () {
    $invoiceId = DB::table('invoices')->insertGetId(invoiceRow(['number' => 'EW-2026-00010', 'status' => 'cancelled']));
    $creditId = DB::table('invoices')->insertGetId(invoiceRow([
        'number' => 'EW-2026-00011', 'is_credit_note' => true, 'credit_note_for_invoice_id' => $invoiceId,
    ]));
    DB::table('invoices')->where('id', $invoiceId)->update(['cancellation_credit_note_id' => $creditId]);

    expect(DB::table('invoices')->where('id', $creditId)->value('credit_note_for_invoice_id'))->toBe($invoiceId);
});

it('rejette un avoir référençant une facture inexistante (self-FK)', function () {
    expect(fn () => DB::table('invoices')->insert(invoiceRow(['credit_note_for_invoice_id' => 999999])))
        ->toThrow(QueryException::class);
});

it('supprime les lignes en cascade à la suppression de la facture', function () {
    $invoiceId = DB::table('invoices')->insertGetId(invoiceRow());
    DB::table('invoice_lines')->insert(invoiceLineRow(['invoice_id' => $invoiceId]));

    DB::table('invoices')->where('id', $invoiceId)->delete();

    expect(DB::table('invoice_lines')->where('invoice_id', $invoiceId)->count())->toBe(0);
});

it('empêche la suppression d\'une facture ayant un paiement (restrict, intégrité comptable)', function () {
    $invoiceId = DB::table('invoices')->insertGetId(invoiceRow(['number' => 'EW-2026-00020', 'status' => 'paid']));
    DB::table('payments')->insert([
        'invoice_id' => $invoiceId, 'amount' => 394.20, 'paid_at' => '2026-06-01', 'method' => 'transfer',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('invoices')->where('id', $invoiceId)->delete())
        ->toThrow(QueryException::class);
});

it('rejette un payments.method hors énumération (CHECK)', function () {
    $invoiceId = DB::table('invoices')->insertGetId(invoiceRow());

    expect(fn () => DB::table('payments')->insert([
        'invoice_id' => $invoiceId, 'amount' => 10, 'paid_at' => '2026-06-01', 'method' => 'bitcoin',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

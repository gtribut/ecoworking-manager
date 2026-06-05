<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Tests de schéma (niveau DB) pour la Phase 1 « Identité & accès ».
 * On insère en SQL brut pour valider les contraintes du data_model §4.1 / §6,
 * indépendamment des modèles Eloquent (pas encore écrits).
 */

function identityUserRow(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jean',
        'last_name' => 'Test',
        'email' => 'user_'.uniqid().'@ecoworking.fr',
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function identityProfileRow(array $overrides = []): array
{
    return array_merge([
        'company_id' => null,
        'desk_id' => null,
        'status' => 'active',
        'show_in_directory' => false,
        'newsletter_opt_in' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function identityCompanyRow(array $overrides = []): array
{
    return array_merge([
        'entity_type' => 'company',
        'status' => 'active',
        'legal_name' => 'Acme SARL',
        'country' => 'FR',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('impose un email unique sur users', function () {
    DB::table('users')->insert(identityUserRow(['email' => 'dup@ecoworking.fr']));

    expect(fn () => DB::table('users')->insert(identityUserRow(['email' => 'dup@ecoworking.fr'])))
        ->toThrow(QueryException::class);
});

it('impose un calendar_token unique mais tolère plusieurs NULL', function () {
    // Deux NULL doivent coexister (token optionnel)
    DB::table('users')->insert(identityUserRow());
    DB::table('users')->insert(identityUserRow());

    DB::table('users')->insert(identityUserRow(['calendar_token' => 'TOKEN-AAA']));

    expect(fn () => DB::table('users')->insert(identityUserRow(['calendar_token' => 'TOKEN-AAA'])))
        ->toThrow(QueryException::class);
});

it('impose une relation 1-1 user↔member_profile (user_id unique)', function () {
    $userId = DB::table('users')->insertGetId(identityUserRow());

    DB::table('member_profiles')->insert(identityProfileRow(['user_id' => $userId]));

    expect(fn () => DB::table('member_profiles')->insert(identityProfileRow(['user_id' => $userId])))
        ->toThrow(QueryException::class);
});

it('impose un bureau unique par membre (desk_id unique, §6.10)', function () {
    $u1 = DB::table('users')->insertGetId(identityUserRow());
    $u2 = DB::table('users')->insertGetId(identityUserRow());

    DB::table('member_profiles')->insert(identityProfileRow(['user_id' => $u1, 'desk_id' => 42]));

    expect(fn () => DB::table('member_profiles')->insert(identityProfileRow(['user_id' => $u2, 'desk_id' => 42])))
        ->toThrow(QueryException::class);
});

it('rejette un member_profiles.status hors énumération (CHECK)', function () {
    $userId = DB::table('users')->insertGetId(identityUserRow());

    expect(fn () => DB::table('member_profiles')->insert(identityProfileRow(['user_id' => $userId, 'status' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('rejette un companies.entity_type hors énumération (CHECK)', function () {
    expect(fn () => DB::table('companies')->insert(identityCompanyRow(['entity_type' => 'bogus'])))
        ->toThrow(QueryException::class);
});

it('rejette un contacts.role hors énumération (CHECK)', function () {
    $companyId = DB::table('companies')->insertGetId(identityCompanyRow());

    expect(fn () => DB::table('contacts')->insert([
        'company_id' => $companyId,
        'first_name' => 'Paul',
        'last_name' => 'Contact',
        'role' => 'bogus',
        'is_primary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('accepte une chaîne identité valide complète (user → company → profile → contact → consent)', function () {
    $companyId = DB::table('companies')->insertGetId(identityCompanyRow());
    $userId = DB::table('users')->insertGetId(identityUserRow(['calendar_token' => 'TOK-OK']));

    DB::table('member_profiles')->insert(identityProfileRow([
        'user_id' => $userId,
        'company_id' => $companyId,
        'desk_id' => 7,
        'job_title' => 'Développeur',
        'show_in_directory' => true,
    ]));

    DB::table('contacts')->insert([
        'company_id' => $companyId,
        'user_id' => $userId,
        'first_name' => 'Jean',
        'last_name' => 'Test',
        'email' => 'billing@acme.fr',
        'role' => 'billing',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('consents')->insert([
        'user_id' => $userId,
        'type' => 'newsletter',
        'granted' => true,
        'granted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('member_profiles')->where('user_id', $userId)->exists())->toBeTrue()
        ->and(DB::table('contacts')->where('company_id', $companyId)->count())->toBe(1)
        ->and(DB::table('consents')->where('user_id', $userId)->value('granted'))->toBeTrue();
});

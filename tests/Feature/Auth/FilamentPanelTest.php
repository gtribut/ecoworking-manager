<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * C3.1 — Back-office Filament : accès admin-only + 2FA TOTP obligatoire.
 * ADR-0002 (auth séparée par contexte) / CLAUDE.md §3.1 (isolation).
 */

/** Le panel `admin` est bien enregistré (id stable). */
it('enregistre le panel admin', function () {
    expect(Filament::getPanel('admin')->getId())->toBe('admin');
});

/** Isolation critique : seul un admin franchit `canAccessPanel`. */
it('autorise un admin à accéder au panel', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('refuse au membre (résident) l\'accès au panel', function () {
    $member = User::factory()->member()->create();

    expect($member->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('refuse à un externe l\'accès au panel', function () {
    $external = User::factory()->external()->create();

    expect($external->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

/** Un membre authentifié atteignant le back-office est rejeté (403). */
it('renvoie 403 quand un membre authentifié ouvre le panel', function () {
    $member = User::factory()->member()->create();

    actingAs($member)->get('/admin')->assertForbidden();
});

/** Le 2FA TOTP est exigé (setUp requis) sur le panel admin. */
it('exige le 2FA multi-facteur sur le panel admin', function () {
    expect(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
});

/** La page de login Filament est publique (guest). */
it('expose une page de login publique', function () {
    get('/admin/login')->assertSuccessful();
});

/**
 * 2FA Filament : le secret TOTP fait l'aller-retour chiffré et reste distinct
 * des colonnes Fortify (portail membre).
 */
it('stocke et relit le secret 2FA Filament (chiffré, distinct de Fortify)', function () {
    $admin = User::factory()->admin()->create();

    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $admin->refresh();
    expect($admin->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        // Mécanisme distinct : Fortify reste vierge côté admin.
        ->and($admin->two_factor_secret)->toBeNull()
        // Au repos, la colonne est chiffrée (≠ valeur claire).
        ->and($admin->getRawOriginal('app_authentication_secret'))->not->toBe('JBSWY3DPEHPK3PXP');

    $admin->saveAppAuthenticationSecret(null);
    $admin->refresh();
    expect($admin->getAppAuthenticationSecret())->toBeNull();
});

it('stocke et relit les codes de récupération 2FA Filament', function () {
    $admin = User::factory()->admin()->create();
    $codes = ['aaaa-bbbb', 'cccc-dddd'];

    $admin->saveAppAuthenticationRecoveryCodes($codes);

    $admin->refresh();
    expect($admin->getAppAuthenticationRecoveryCodes())->toBe($codes);
});

it('expose l\'email comme libellé d\'authentificateur', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@ecoworking.fr']);

    expect($admin->getAppAuthenticationHolderName())->toBe('admin@ecoworking.fr');
});

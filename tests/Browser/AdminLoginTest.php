<?php

declare(strict_types=1);

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

it('connecte un admin (mot de passe + 2FA TOTP) et affiche le dashboard', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();

    // Purge l'anti-rejeu TOTP (cache file persistant entre tests, cf. helpers.php).
    Cache::flush();

    visit('/admin/login')
        ->assertSee('Connectez-vous à votre compte')
        ->type('input[type=email]', $admin->email)
        ->type('input[type=password]', 'password')
        ->click('Connexion')
        // Challenge 2FA obligatoire (MFA native Filament, C3.1).
        ->waitForText('Vérifier votre identité')
        ->type('input[autocomplete=one-time-code]', currentTotpCode($admin))
        ->click('Confirmer la connexion')
        // Dashboard : widgets KPIs (C12.6) visibles.
        ->waitForText('Membres actifs')
        ->assertPathIs('/admin')
        ->assertSee('Abonnements actifs')
        ->assertSee('Factures en retard');
});

it('refuse un mot de passe invalide', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();

    visit('/admin/login')
        ->type('input[type=email]', $admin->email)
        ->type('input[type=password]', 'mauvais-mot-de-passe')
        ->click('Connexion')
        ->waitForText('Ces identifiants ne correspondent pas à nos enregistrements.')
        ->assertPathContains('/admin/login');
});

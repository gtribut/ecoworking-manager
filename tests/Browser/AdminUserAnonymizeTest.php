<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('anonymise un membre (RGPD) après confirmation forte (C12.7)', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();
    $member = User::factory()->resident()->create([
        'first_name' => 'Ancien',
        'last_name' => 'Coworkeur',
        'email' => 'ancien.coworkeur@example.com',
    ]);

    $page = loginToAdminPanel($admin);

    $page->navigate("/admin/users/{$member->id}/edit")
        ->waitForText('Anonymiser (RGPD)')
        // `text="…"` : les parenthèses feraient interpréter le libellé comme
        // un sélecteur CSS par le plugin (Selector::isExplicit).
        ->click('text="Anonymiser (RGPD)"')
        ->waitForText('Anonymiser cet utilisateur ?')
        ->click('Anonymiser définitivement')
        ->waitForText('Utilisateur anonymisé');

    // PII écrasée, accès révoqués, soft delete (factures conservées).
    $anonymized = User::withTrashed()->findOrFail($member->id);
    expect($anonymized->email)->toEndWith('@ecoworking.invalid')
        ->and($anonymized->anonymized_at)->not->toBeNull()
        ->and($anonymized->deleted_at)->not->toBeNull()
        ->and($anonymized->first_name)->not->toBe('Ancien');

    // Redirigé vers la liste (« Comptes ») : plus aucune PII visible.
    $page->waitForText('Comptes')
        ->assertPathContains('/admin/users')
        ->assertDontSee('Ancien Coworkeur')
        ->assertDontSee('ancien.coworkeur@example.com');
});

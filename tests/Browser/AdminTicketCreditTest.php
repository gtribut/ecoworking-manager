<?php

declare(strict_types=1);

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('crédite manuellement des tickets à un membre (C12.2)', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();
    $member = User::factory()->resident()->create([
        'first_name' => 'Léa',
        'last_name' => 'Cliente',
    ]);

    $page = loginToAdminPanel($admin);

    $page->navigate('/admin/tickets')
        ->waitForText('Crédit manuel')
        ->click('Crédit manuel')
        ->waitForText('Créditer manuellement des tickets')
        // Membre : select recherchable Filament (combobox custom) — on ouvre
        // le premier combobox (placeholder) puis on choisit l'option.
        ->click('Sélectionnez une option')
        ->waitForText('Léa Cliente')
        // (l'option est rendue deux fois par le combobox, dont une cachée)
        ->click('text=Léa Cliente >> visible=true >> nth=0')
        // Type de ticket + quantité + raison.
        ->select('select[id$="type"]', 'desk_half_day')
        ->type('input[id$="quantity"]', '3')
        ->type('textarea[id$="reason"]', 'Geste commercial e2e')
        ->click('Soumettre')
        ->waitForText('Tickets crédités');

    // 3 tickets créés, disponibles, tracés comme crédit manuel.
    $tickets = Ticket::query()->where('user_id', $member->id)->get();
    expect($tickets)->toHaveCount(3)
        ->and($tickets->first()->status)->toBe(TicketStatus::Available)
        ->and($tickets->first()->credited_by)->toBe($admin->id)
        ->and($tickets->first()->credit_reason)->toBe('Geste commercial e2e');

    // Et visibles dans la liste des tickets.
    $page->navigate('/admin/tickets')
        ->waitForText('Léa Cliente')
        ->assertSee('Crédit manuel');
});

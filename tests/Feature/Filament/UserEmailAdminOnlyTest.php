<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.9 — Décision D (2026-07-02, docs/review_fable/07 §2) : le changement
 * d'email d'un membre est un acte admin exclusif (back-office User). Aucun
 * flux self-service côté portail (PRD §3.4.5).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('permet à un admin de modifier l\'email d\'un membre via le back-office', function () {
    actingAs(User::factory()->admin()->create());
    $member = User::factory()->member()->create();

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->fillForm(['email' => 'nouvelle.adresse@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->refresh()->email)->toBe('nouvelle.adresse@example.com');
});

it('refuse un email déjà utilisé par un autre utilisateur (unicité)', function () {
    actingAs(User::factory()->admin()->create());
    $existing = User::factory()->member()->create();
    $member = User::factory()->member()->create();

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->fillForm(['email' => $existing->email])
        ->call('save')
        ->assertHasFormErrors(['email']);

    expect($member->refresh()->email)->not->toBe($existing->email);
});

it('ignore l\'email envoyé par un membre sur PATCH /api/profile (aucune écriture self-service)', function () {
    $member = User::factory()->member()->create();
    $originalEmail = $member->email;

    actingAs($member)
        ->patchJson('/api/profile', [
            'email' => 'pirate@example.com',
            'theme' => 'dark',
        ])
        ->assertOk();

    expect($member->refresh())
        ->email->toBe($originalEmail)
        ->theme->toBe('dark');
});

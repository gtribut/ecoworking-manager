<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Services\AnonymizeUserService;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C12.7 — Action Filament « Anonymiser (RGPD) » sur la page d'édition d'un
 * compte (PRD §5.6). Admin uniquement, confirmation forte, désactivée si
 * déjà anonymisé, jamais sur soi-même (anti lock-out).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('anonymise un membre via l\'action de la page d\'édition', function () {
    actingAs(User::factory()->admin()->create());
    $member = User::factory()->member()->create(['email' => 'a.anonymiser@example.com']);

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->callAction('anonymize')
        ->assertNotified('Utilisateur anonymisé');

    $fresh = User::withTrashed()->findOrFail($member->id);
    expect($fresh->anonymized_at)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->email)->not->toBe('a.anonymiser@example.com');
});

it('masque l\'action si l\'utilisateur est déjà anonymisé', function () {
    actingAs(User::factory()->admin()->create());
    $member = User::factory()->member()->create();
    app(AnonymizeUserService::class)->anonymize($member);

    // La page reste consultable (record soft-deleted résolu withTrashed),
    // mais l'action n'est plus proposée (UserPolicy::anonymize → false).
    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->assertActionHidden('anonymize');
});

it('masque l\'action sur son propre compte (anti lock-out)', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden('anonymize');
});

it('refuse la page d\'édition (et donc l\'action) à un non-admin', function () {
    actingAs(User::factory()->member()->create());
    $target = User::factory()->member()->create();

    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->assertForbidden();
});

it('exige une confirmation avant d\'anonymiser', function () {
    actingAs(User::factory()->admin()->create());
    $member = User::factory()->member()->create();

    // Le simple montage de l'action ouvre la modale de confirmation : rien
    // ne doit être écrit tant que la confirmation n'est pas soumise.
    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->mountAction('anonymize')
        ->assertActionMounted('anonymize');

    expect($member->refresh()->anonymized_at)->toBeNull()
        ->and($member->trashed())->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\MemberProfile;
use App\Models\User;

/**
 * C3.2 — Policies des Resources gérées en back-office. Garantit que les actes
 * de gestion (création/édition/suppression de comptes, contacts, profils) sont
 * réservés à l'admin (CLAUDE.md §3.1). Les cas d'isolation membre A/B vivent
 * dans AuthorizationTest.
 */
it('réserve la gestion des comptes à l\'admin (UserPolicy)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $target = User::factory()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue()
        ->and($admin->can('create', User::class))->toBeTrue()
        ->and($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeTrue()
        // Garde-fou anti lock-out : pas d'auto-suppression.
        ->and($admin->can('delete', $admin))->toBeFalse()
        // Suppression définitive interdite (factures 10 ans, §3.4).
        ->and($admin->can('forceDelete', $target))->toBeFalse();

    expect($member->can('viewAny', User::class))->toBeFalse()
        ->and($member->can('create', User::class))->toBeFalse()
        ->and($member->can('update', $target))->toBeFalse();
});

it('réserve la gestion des contacts à l\'admin (ContactPolicy)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $contact = Contact::factory()->create();

    expect($admin->can('viewAny', Contact::class))->toBeTrue()
        ->and($admin->can('create', Contact::class))->toBeTrue()
        ->and($admin->can('update', $contact))->toBeTrue()
        ->and($admin->can('delete', $contact))->toBeTrue();

    expect($member->can('viewAny', Contact::class))->toBeFalse()
        ->and($member->can('view', $contact))->toBeFalse()
        ->and($member->can('update', $contact))->toBeFalse();
});

it('réserve la création/suppression de profils à l\'admin (MemberProfilePolicy)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $profile = MemberProfile::factory()->create();

    expect($admin->can('create', MemberProfile::class))->toBeTrue()
        ->and($admin->can('delete', $profile))->toBeTrue();

    expect($member->can('create', MemberProfile::class))->toBeFalse()
        ->and($member->can('delete', $profile))->toBeFalse();

    // Le propriétaire édite son propre profil (règle d'isolation conservée).
    expect($profile->user->can('update', $profile))->toBeTrue();
});

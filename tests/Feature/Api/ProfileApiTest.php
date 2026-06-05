<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\MemberProfile;
use App\Models\User;

/** C4.2 — Endpoints profil membre (lecture / édition partielle, auto-scopé). */
it('protège le profil : 401 si non authentifié', function () {
    $this->getJson('/api/profile')->assertUnauthorized();
    $this->patchJson('/api/profile', ['bio' => 'x'])->assertUnauthorized();
});

it('renvoie le profil du membre courant avec son entité (lecture seule)', function () {
    $company = Company::factory()->create(['legal_name' => 'Acme SCOP']);
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create([
        'company_id' => $company->id,
        'job_title' => 'Designer',
        'show_in_directory' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('profile.job_title', 'Designer')
        ->assertJsonPath('company.legal_name', 'Acme SCOP')
        ->assertJsonStructure(['user' => ['id', 'first_name', 'email', 'theme'], 'profile', 'company']);
});

it('met à jour les champs personnels et le thème du membre courant', function () {
    $user = User::factory()->resident()->create(['theme' => null]);
    MemberProfile::factory()->for($user)->create(['bio' => 'ancien', 'newsletter_opt_in' => false]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'bio' => 'Nouveau bio',
            'newsletter_opt_in' => true,
            'theme' => 'dark',
        ])
        ->assertOk()
        ->assertJsonPath('profile.bio', 'Nouveau bio')
        ->assertJsonPath('profile.newsletter_opt_in', true)
        ->assertJsonPath('user.theme', 'dark');

    expect($user->fresh()->theme)->toBe('dark')
        ->and($user->memberProfile->fresh()->bio)->toBe('Nouveau bio');
});

it('valide les entrées du profil (URL invalide → 422)', function () {
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patchJson('/api/profile', ['linkedin_url' => 'pas-une-url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('linkedin_url');
});

it('n\'affecte que le profil du membre courant (isolation)', function () {
    $a = User::factory()->resident()->create();
    MemberProfile::factory()->for($a)->create(['bio' => 'A']);
    $b = User::factory()->resident()->create();
    MemberProfile::factory()->for($b)->create(['bio' => 'B']);

    $this->actingAs($a)->patchJson('/api/profile', ['bio' => 'A modifié'])->assertOk();

    expect($a->memberProfile->fresh()->bio)->toBe('A modifié')
        ->and($b->memberProfile->fresh()->bio)->toBe('B');
});

it('renvoie 409 si le compte n\'a pas de profil membre éditable', function () {
    $user = User::factory()->billingContact()->create(); // pas de member_profile

    $this->actingAs($user)
        ->patchJson('/api/profile', ['bio' => 'x'])
        ->assertStatus(409);

    // La lecture reste possible (profil null).
    $this->actingAs($user)->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('profile', null);
});

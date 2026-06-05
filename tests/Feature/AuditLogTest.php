<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Resource;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('journalise les modifications des entités sensibles', function () {
    $company = Company::factory()->create();

    $company->update(['status' => 'inactive']);

    $activity = Activity::forSubject($company)->forEvent('updated')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['status'])->toBe('inactive')
        ->and($activity->attribute_changes['old']['status'])->toBe('active');
});

it('journalise la création des entités sensibles', function () {
    $company = Company::factory()->create();

    expect(Activity::forSubject($company)->forEvent('created')->exists())->toBeTrue();
});

it('ne journalise jamais le mot de passe ni les secrets (RGPD §3.4)', function () {
    $user = User::factory()->create();

    // Changement de mot de passe seul → hors liste blanche → aucun log « updated ».
    $user->update(['password' => 'nouveau-mot-de-passe-très-secret']);

    expect(Activity::forSubject($user)->forEvent('updated')->exists())->toBeFalse();

    // Un changement audité (email) ne doit jamais embarquer de secret.
    $user->update(['email' => 'nouvelle-adresse@ecoworking.fr']);
    $activity = Activity::forSubject($user)->forEvent('updated')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes'])->toHaveKey('email')
        ->and($activity->attribute_changes['attributes'])->not->toHaveKeys(['password', 'two_factor_secret', 'remember_token']);
});

it('ne journalise pas les entités non sensibles', function () {
    // Un Resource n'a pas le trait Auditable : aucune activité créée.
    $countBefore = Activity::count();
    Resource::factory()->create();

    expect(Activity::count())->toBe($countBefore);
});

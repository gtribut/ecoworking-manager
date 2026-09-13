<?php

declare(strict_types=1);

use App\Enums\Period;
use App\Models\Company;
use App\Models\DeskAbsence;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
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

/**
 * Lot C (PRD §3.4.6 / §3.4.5) : les absences et les champs sensibles du profil
 * (opt-in newsletter, visibilité annuaire) sont tracés — y compris la
 * suppression d'une absence, seule trace restante une fois la ligne effacée.
 */
it('journalise la création, la modification et la suppression d\'une absence', function () {
    $absence = DeskAbsence::factory()->create([
        'date_start' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'notes' => 'Déplacement client',
    ]);

    expect(Activity::forSubject($absence)->forEvent('created')->exists())->toBeTrue();

    $absence->update(['period' => Period::Morning->value, 'notes' => 'Formation']);

    $updated = Activity::forSubject($absence)->forEvent('updated')->latest('id')->first();

    expect($updated)->not->toBeNull()
        ->and($updated->attribute_changes['attributes']['period'])->toBe(Period::Morning->value)
        ->and($updated->attribute_changes['old']['period'])->toBe(Period::FullDay->value)
        ->and($updated->attribute_changes['attributes']['notes'])->toBe('Formation');

    $id = $absence->id;
    $absence->delete();

    expect(Activity::query()->where('subject_type', 'desk_absence')->where('subject_id', $id)
        ->where('event', 'deleted')->exists())->toBeTrue();
});

it('ne journalise pas de faux changement de booléen à la création du profil', function () {
    // Piège documenté du trait Auditable : sans défaut explicite sur le modèle,
    // la première écriture serait journalisée `null → false`.
    $profile = MemberProfile::factory()->create();
    $profile->update(['job_title' => 'Développeuse']); // hors liste blanche

    expect(Activity::forSubject($profile)->forEvent('updated')->exists())->toBeFalse();
});

it('journalise l\'opt-out RGPD du profil membre (annuaire, newsletter)', function () {
    $profile = MemberProfile::factory()->create([
        'show_in_directory' => true,
        'newsletter_opt_in' => true,
    ]);

    $profile->update(['show_in_directory' => false, 'newsletter_opt_in' => false]);

    $activity = Activity::forSubject($profile)->forEvent('updated')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['show_in_directory'])->toBeFalse()
        ->and($activity->attribute_changes['old']['show_in_directory'])->toBeTrue()
        ->and($activity->attribute_changes['attributes']['newsletter_opt_in'])->toBeFalse()
        ->and($activity->attribute_changes['old']['newsletter_opt_in'])->toBeTrue();
});

it('journalise les opt-in du profil membre (newsletter, annuaire)', function () {
    $profile = MemberProfile::factory()->create([
        'show_in_directory' => false,
        'newsletter_opt_in' => false,
    ]);

    $profile->update(['show_in_directory' => true, 'newsletter_opt_in' => true]);

    $activity = Activity::forSubject($profile)->forEvent('updated')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['show_in_directory'])->toBeTrue()
        ->and($activity->attribute_changes['old']['show_in_directory'])->toBeFalse()
        ->and($activity->attribute_changes['attributes']['newsletter_opt_in'])->toBeTrue()
        // Jamais de PII inutile dans le journal (CLAUDE.md §3.4).
        ->and($activity->attribute_changes['attributes'])->not->toHaveKeys(['bio', 'birth_date', 'photo_path', 'admin_notes']);
});

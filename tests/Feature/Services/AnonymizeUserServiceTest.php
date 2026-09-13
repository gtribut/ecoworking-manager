<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\AnonymizeUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\postJson;

/**
 * C12.7 — Anonymisation RGPD (PRD §5.6, CLAUDE.md §3.4) : écrasement PII,
 * conservation comptable, révocation des accès, audit sans PII.
 */
it('efface la PII du compte et du profil membre (PRD §5.6)', function () {
    // Disque de stockage des photos (ProfilePhotoService) : les trois rendus
    // vivent sous le préfixe porté par `photo_path`.
    $uploadDisk = config('filesystems.default');
    Storage::fake($uploadDisk);
    foreach ([80, 200, 400] as $size) {
        Storage::disk($uploadDisk)->put("profile-photos/jean-uuid/{$size}.webp", 'fake-image');
    }

    $user = User::factory()->member()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'calendar_token' => str_repeat('ab', 24),
        'two_factor_secret' => encrypt('totp-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $profile = MemberProfile::factory()->for($user)->create([
        'photo_path' => 'profile-photos/jean-uuid',
        'birth_date' => '1990-05-12',
        'job_title' => 'Développeur',
        'bio' => 'Ma biographie personnelle',
        'interests' => 'vélo, photo',
        'linkedin_url' => 'https://linkedin.com/in/jeandupont',
        'website_url' => 'https://jeandupont.fr',
        'admin_notes' => 'Note interne sensible',
        'show_in_directory' => true,
        'newsletter_opt_in' => true,
    ]);

    app(AnonymizeUserService::class)->anonymize($user);

    $user->refresh();
    expect($user->first_name)->toBe('Utilisateur')
        ->and($user->last_name)->toBe('anonymisé')
        ->and($user->email)->toStartWith('deleted-')
        ->and($user->email)->toEndWith('@ecoworking.invalid')
        ->and($user->anonymized_at)->not->toBeNull()
        ->and($user->trashed())->toBeTrue()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->calendar_token)->toBeNull()
        ->and($user->last_login_at)->toBeNull();

    $profile->refresh();
    expect($profile->photo_path)->toBeNull()
        ->and($profile->birth_date)->toBeNull()
        ->and($profile->job_title)->toBeNull()
        ->and($profile->bio)->toBeNull()
        ->and($profile->interests)->toBeNull()
        ->and($profile->linkedin_url)->toBeNull()
        ->and($profile->website_url)->toBeNull()
        ->and($profile->admin_notes)->toBeNull()
        ->and($profile->show_in_directory)->toBeFalse()
        ->and($profile->newsletter_opt_in)->toBeFalse();

    // Photo supprimée du disque (PRD §5.6-2).
    Storage::disk($uploadDisk)->assertMissing('profile-photos/jean-uuid/80.webp');
    Storage::disk($uploadDisk)->assertMissing('profile-photos/jean-uuid/200.webp');
    Storage::disk($uploadDisk)->assertMissing('profile-photos/jean-uuid/400.webp');
});

it('conserve les factures et leurs lignes à l\'identique (conformité fiscale 10 ans)', function () {
    $user = User::factory()->member()->create();
    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'billing_name' => 'Jean Dupont',
        'subtotal_ht' => '100.00',
        'total_vat' => '20.00',
        'total_ttc' => '120.00',
    ]);
    $line = InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 1,
        'unit_price_ht' => 100,
        'vat_rate' => 20,
        'line_total_ht' => 100,
        'line_vat' => 20,
        'line_total_ttc' => 120,
    ]);
    $number = $invoice->number;
    $description = $line->description;

    app(AnonymizeUserService::class)->anonymize($user);

    $invoice->refresh();
    // La facture reste figée : numéro, montants, snapshot d'adresse et
    // rattachement billable intacts (PRD §5.6-3).
    expect($invoice->number)->toBe($number)
        ->and($invoice->billing_name)->toBe('Jean Dupont')
        ->and((float) $invoice->subtotal_ht)->toBe(100.00)
        ->and((float) $invoice->total_vat)->toBe(20.00)
        ->and((float) $invoice->total_ttc)->toBe(120.00)
        ->and($invoice->billable_type)->toBe('user')
        ->and($invoice->billable_id)->toBe($user->id)
        ->and($invoice->lines()->count())->toBe(1);

    $line->refresh();
    expect($line->description)->toBe($description)
        ->and((float) $line->line_total_ttc)->toBe(120.00);
});

it('empêche toute reconnexion après anonymisation (PRD §5.6-4)', function () {
    $user = User::factory()->member()->create(['email' => 'ancien.membre@ecoworking.fr']);

    app(AnonymizeUserService::class)->anonymize($user);

    // Identifiants d'origine (mot de passe factory « password ») → refus.
    postJson('/login', ['email' => 'ancien.membre@ecoworking.fr', 'password' => 'password'])
        ->assertStatus(422);
    $this->assertGuest();

    // Le mot de passe stocké n'est plus l'ancien (valeur aléatoire hashée).
    $fresh = User::withTrashed()->findOrFail($user->id);
    expect(Hash::check('password', $fresh->password))->toBeFalse();
});

it('révoque tokens Sanctum, sessions actives et jetons de reset', function () {
    $user = User::factory()->member()->create(['email' => 'revoque@ecoworking.fr']);
    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => 'user',
        'tokenable_id' => $user->id,
        'name' => 'test-token',
        'token' => hash('sha256', 'token-secret'),
        'abilities' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'session-a-revoquer',
        'user_id' => $user->id,
        'payload' => base64_encode('payload'),
        'last_activity' => now()->getTimestamp(),
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => 'revoque@ecoworking.fr',
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    app(AnonymizeUserService::class)->anonymize($user);

    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', 'revoque@ecoworking.fr')->count())->toBe(0);
});

it('refuse une seconde anonymisation du même utilisateur', function () {
    $user = User::factory()->member()->create();

    app(AnonymizeUserService::class)->anonymize($user);

    // Le service re-résout l'utilisateur (withTrashed) : le double appel
    // doit être refusé explicitement, pas ré-écraser silencieusement.
    expect(fn () => app(AnonymizeUserService::class)->anonymize($user))
        ->toThrow(RuntimeException::class, 'déjà anonymisé');
});

it('trace l\'anonymisation dans l\'audit log sans aucune PII', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->member()->create([
        'first_name' => 'Jeanne',
        'last_name' => 'Durand',
        'email' => 'jeanne.durand@example.com',
    ]);

    app(AnonymizeUserService::class)->anonymize($user, $admin);

    // Une seule activité « anonymized », causée par l'admin, sans diff.
    $activity = Activity::forSubject($user)->forEvent('anonymized')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->description)->toContain('anonymisé')
        ->and($activity->attribute_changes)->toBeEmpty();

    // Pas d'événement `updated`/`deleted` issu de l'écrasement : les
    // anciennes valeurs PII ne doivent PAS partir en diff d'audit.
    expect(Activity::forSubject($user)->forEvent('updated')->exists())->toBeFalse()
        ->and(Activity::forSubject($user)->forEvent('deleted')->exists())->toBeFalse();

    // Aucune PII d'origine dans l'entrée d'anonymisation.
    $raw = json_encode($activity->getAttributes(), JSON_THROW_ON_ERROR);
    expect($raw)->not->toContain('Jeanne')
        ->and($raw)->not->toContain('Durand')
        ->and($raw)->not->toContain('jeanne.durand@example.com');
});

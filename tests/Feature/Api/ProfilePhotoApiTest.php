<?php

declare(strict_types=1);

use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Lot F — Photo de profil (PRD §3.4.2 / §3.7.3).
 *
 * Traitement serveur (3 rendus carrés, EXIF supprimés), stockage privé et
 * lecture autorisée au cas par cas : soi-même, admin, ou membre habilité à
 * l'annuaire regardant un profil opt-in. Tout le reste répond 404.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake(config('filesystems.default'));
});

/** JPEG réel (GD) porteur d'un segment APP1 « EXIF » reconnaissable. */
function jpegWithExif(int $width = 320, int $height = 240): UploadedFile
{
    $canvas = imagecreatetruecolor($width, $height);
    imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 12, 140, 200));

    ob_start();
    imagejpeg($canvas, null, 90);
    $jpeg = (string) ob_get_clean();
    imagedestroy($canvas);

    $payload = "Exif\x00\x00".str_repeat("\x00", 6).'GPS-LATITUDE-45-7578-ECOWORKING';
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

    return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
}

function memberWithProfile(array $profile = []): User
{
    $user = User::factory()->resident()->create();
    MemberProfile::factory()->for($user)->create($profile);

    return $user;
}

it('refuse l\'upload et la suppression à un anonyme (401)', function () {
    $this->postJson('/api/profile/photo')->assertUnauthorized();
    $this->deleteJson('/api/profile/photo')->assertUnauthorized();
});

it('écrit les trois rendus carrés et renvoie les URLs autorisées', function () {
    $user = memberWithProfile();

    $response = $this->actingAs($user)
        ->postJson('/api/profile/photo', ['photo' => jpegWithExif(600, 400)])
        ->assertOk()
        ->assertJsonPath('photo.sm', "/api/users/{$user->id}/photo/80")
        ->assertJsonPath('photo.md', "/api/users/{$user->id}/photo/200")
        ->assertJsonPath('photo.lg', "/api/users/{$user->id}/photo/400");

    $prefix = $user->memberProfile->fresh()->photo_path;
    expect($prefix)->toStartWith('profile-photos/');

    $disk = Storage::disk(config('filesystems.default'));
    foreach ([80, 200, 400] as $size) {
        $disk->assertExists("{$prefix}/{$size}.webp");

        // Carré exact : recadrage centré côté serveur, jamais côté client.
        $dimensions = getimagesizefromstring($disk->get("{$prefix}/{$size}.webp"));
        expect($dimensions[0])->toBe($size)->and($dimensions[1])->toBe($size);
    }

    // Rien du chemin de stockage ne fuit dans la réponse.
    expect($response->json('photo.md'))->not->toContain($prefix);
});

it('supprime les métadonnées EXIF (géolocalisation) au ré-encodage', function () {
    $user = memberWithProfile();

    $this->actingAs($user)->postJson('/api/profile/photo', ['photo' => jpegWithExif()])->assertOk();

    $prefix = $user->memberProfile->fresh()->photo_path;
    $stored = Storage::disk(config('filesystems.default'))->get("{$prefix}/400.webp");

    expect($stored)->not->toContain('GPS-LATITUDE-45-7578-ECOWORKING')
        ->and($stored)->not->toContain('Exif');
});

it('refuse une image de plus de 2 Mo (422)', function () {
    $user = memberWithProfile();

    $this->actingAs($user)
        ->postJson('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('grande.jpg', 800, 800)->size(2100),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');

    expect($user->memberProfile->fresh()->photo_path)->toBeNull();
});

it('refuse un format non autorisé (422)', function () {
    $user = memberWithProfile();

    $this->actingAs($user)
        ->postJson('/api/profile/photo', [
            'photo' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('refuse une image plus petite que 80 × 80 (422)', function () {
    $user = memberWithProfile();

    $this->actingAs($user)
        ->postJson('/api/profile/photo', ['photo' => UploadedFile::fake()->image('mini.png', 60, 60)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('purge l\'ancienne photo lors d\'un remplacement', function () {
    $user = memberWithProfile();
    $disk = Storage::disk(config('filesystems.default'));

    $this->actingAs($user)->postJson('/api/profile/photo', ['photo' => jpegWithExif()])->assertOk();
    $first = $user->memberProfile->fresh()->photo_path;

    $this->actingAs($user)->postJson('/api/profile/photo', ['photo' => jpegWithExif()])->assertOk();
    $second = $user->memberProfile->fresh()->photo_path;

    expect($second)->not->toBe($first);
    $disk->assertMissing("{$first}/400.webp");
    $disk->assertExists("{$second}/400.webp");
});

it('supprime la photo et ses trois rendus (DELETE)', function () {
    $user = memberWithProfile();
    $this->actingAs($user)->postJson('/api/profile/photo', ['photo' => jpegWithExif()])->assertOk();
    $prefix = $user->memberProfile->fresh()->photo_path;

    $this->actingAs($user)->deleteJson('/api/profile/photo')
        ->assertOk()
        ->assertJsonPath('photo', null);

    expect($user->memberProfile->fresh()->photo_path)->toBeNull();
    foreach ([80, 200, 400] as $size) {
        Storage::disk(config('filesystems.default'))->assertMissing("{$prefix}/{$size}.webp");
    }
});

it('renvoie 409 si le compte n\'a pas de profil membre', function () {
    $user = User::factory()->billingContact()->create();

    $this->actingAs($user)
        ->postJson('/api/profile/photo', ['photo' => jpegWithExif()])
        ->assertStatus(409);
});

/*
|--------------------------------------------------------------------------
| Lecture : GET /api/users/{user}/photo/{size}
|--------------------------------------------------------------------------
*/

/** Dépose une photo pour un utilisateur donné, retourne son préfixe. */
function uploadPhotoFor(User $user): string
{
    test()->actingAs($user)->postJson('/api/profile/photo', ['photo' => jpegWithExif()])->assertOk();

    return (string) $user->memberProfile->fresh()->photo_path;
}

it('sert sa propre photo, en cache privé', function () {
    $user = memberWithProfile(['show_in_directory' => false]);
    uploadPhotoFor($user);

    $this->actingAs($user)
        ->get("/api/users/{$user->id}/photo/200")
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=900, private');
});

it('sert la photo d\'un membre opt-in à un autre membre de l\'annuaire', function () {
    $target = memberWithProfile(['show_in_directory' => true]);
    uploadPhotoFor($target);
    $viewer = memberWithProfile();

    $this->actingAs($viewer)->get("/api/users/{$target->id}/photo/80")->assertOk();
});

it('sert la photo de n\'importe quel membre à un admin', function () {
    $target = memberWithProfile(['show_in_directory' => false]);
    uploadPhotoFor($target);

    $this->actingAs(User::factory()->admin()->create())
        ->get("/api/users/{$target->id}/photo/400")
        ->assertOk();
});

it('répond 404 (jamais 403) à un external, qui n\'a pas accès à l\'annuaire', function () {
    $target = memberWithProfile(['show_in_directory' => true]);
    uploadPhotoFor($target);

    $this->actingAs(User::factory()->external()->create())
        ->get("/api/users/{$target->id}/photo/200")
        ->assertNotFound();
});

it('répond 404 pour un membre qui a refusé l\'annuaire (opt-out)', function () {
    $target = memberWithProfile(['show_in_directory' => false]);
    uploadPhotoFor($target);

    $this->actingAs(memberWithProfile())
        ->get("/api/users/{$target->id}/photo/200")
        ->assertNotFound();
});

it('répond 404 si le membre n\'a pas de photo', function () {
    $target = memberWithProfile(['show_in_directory' => true]);

    $this->actingAs(memberWithProfile())
        ->get("/api/users/{$target->id}/photo/200")
        ->assertNotFound();
});

it('refuse une taille hors catalogue (404 de routage)', function () {
    $user = memberWithProfile();
    uploadPhotoFor($user);

    $this->actingAs($user)->get("/api/users/{$user->id}/photo/1600")->assertNotFound();
});

it('exige une authentification pour lire une photo (401)', function () {
    // Pas d'upload ici : `auth:sanctum` doit répondre AVANT toute logique
    // métier (sinon la session de l'upload resterait ouverte sur ce test).
    $target = memberWithProfile(['show_in_directory' => true]);

    $this->getJson("/api/users/{$target->id}/photo/200")->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Modération admin : retrait d'une photo depuis le back-office
|--------------------------------------------------------------------------
*/

it('permet à l\'admin de retirer la photo d\'un membre depuis sa fiche', function () {
    $member = memberWithProfile();
    $prefix = uploadPhotoFor($member);
    $profile = $member->memberProfile->fresh();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());

    Livewire::test(EditMemberProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('deletePhoto')
        ->assertHasNoActionErrors();

    expect($profile->fresh()->photo_path)->toBeNull();
    Storage::disk(config('filesystems.default'))->assertMissing("{$prefix}/200.webp");
});

it('n\'offre pas le retrait de photo quand le membre n\'en a pas', function () {
    $profile = memberWithProfile()->memberProfile;

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());

    Livewire::test(EditMemberProfile::class, ['record' => $profile->getRouteKey()])
        ->assertActionHidden('deletePhoto');
});

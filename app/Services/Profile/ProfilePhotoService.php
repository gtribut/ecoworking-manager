<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\MemberProfile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;

/**
 * Photos de profil membre (PRD §3.4.2, §3.7.3).
 *
 * Traitement 100 % côté serveur : recadrage carré centré en trois tailles
 * fixes (80 annuaire / 200 fiche / 400 source), ré-encodage WebP (JPEG si GD
 * n'a pas WebP) et **suppression de toutes les métadonnées** (`strip`) — les
 * EXIF d'un smartphone portent la géolocalisation, donnée personnelle qu'on
 * n'a aucune raison de conserver (CLAUDE.md §3.4). L'orientation EXIF est
 * appliquée AVANT le strip (`autoOrientation`) pour ne pas coucher les photos.
 *
 * Stockage sur le disque par défaut (`local` en dev/test, `s3`/Cellar en prod)
 * sous un préfixe non devinable `profile-photos/{uuid}`. Rien n'est public :
 * les fichiers ne sont servis que par `GET /api/users/{user}/photo/{size}`,
 * authentifié et autorisé (cf. UserPolicy::viewPhoto).
 *
 * `member_profiles.photo_path` porte le PRÉFIXE (le dossier), pas un fichier :
 * les trois rendus vivent dedans (`80.webp`, `200.webp`, `400.webp`).
 */
final class ProfilePhotoService
{
    /** Tailles rendues, en pixels (carré). PRD §3.4.2. */
    public const array SIZES = [80, 200, 400];

    /** Racine de stockage, volontairement hors de tout disque public. */
    private const string PREFIX = 'profile-photos';

    /**
     * Remplace la photo d'un profil : écrit les trois rendus, purge l'ancienne
     * version puis persiste le nouveau préfixe. L'ordre (écriture → bascule →
     * purge) évite de laisser un profil sans photo si l'écriture échoue.
     */
    public function store(MemberProfile $profile, UploadedFile $file): string
    {
        $previous = $profile->photo_path;
        $prefix = self::PREFIX.'/'.Str::uuid()->toString();

        $manager = new ImageManager(
            $this->driver(),
            autoOrientation: true,
            decodeAnimation: false,
            strip: true,
        );

        $encoder = $this->encoder();
        $extension = $this->extension();

        foreach (self::SIZES as $size) {
            $rendered = $manager->decodePath($file->getRealPath())
                ->cover($size, $size)
                ->encode($encoder);

            $this->disk()->put($prefix.'/'.$size.'.'.$extension, (string) $rendered);
        }

        $profile->forceFill(['photo_path' => $prefix])->save();

        if ($previous !== null && $previous !== $prefix) {
            $this->purge($previous);
        }

        return $prefix;
    }

    /** Supprime la photo d'un profil (remplacement, retrait volontaire, RGPD). */
    public function delete(MemberProfile $profile): void
    {
        $prefix = $profile->photo_path;

        if ($prefix === null) {
            return;
        }

        $profile->forceFill(['photo_path' => null])->save();

        $this->purge($prefix);
    }

    /**
     * Chemin du rendu demandé sur le disque, ou null s'il n'existe pas.
     * L'extension est résolue à la lecture : un profil photographié avant
     * l'activation de WebP garde ses JPEG.
     */
    public function resolve(string $prefix, int $size): ?string
    {
        if (! in_array($size, self::SIZES, true)) {
            return null;
        }

        foreach (['webp', 'jpg'] as $extension) {
            $path = $prefix.'/'.$size.'.'.$extension;

            if ($this->disk()->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        $disk = config('filesystems.default');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /**
     * URLs de lecture des trois tailles, relatives (même origine que la SPA,
     * ADR-0004) — `null` si le membre n'a pas de photo. Jamais le chemin de
     * stockage : le client ne voit que l'endpoint autorisé.
     *
     * @return array{sm: string, md: string, lg: string}|null
     */
    public static function urls(?string $photoPath, int $userId): ?array
    {
        if ($photoPath === null || $photoPath === '') {
            return null;
        }

        $url = static fn (int $size): string => '/api/users/'.$userId.'/photo/'.$size;

        return ['sm' => $url(80), 'md' => $url(200), 'lg' => $url(400)];
    }

    /** Supprime récursivement un préfixe (toutes tailles, toutes extensions). */
    private function purge(string $prefix): void
    {
        if (! str_starts_with($prefix, self::PREFIX.'/')) {
            // Photo historique déposée par l'admin avant le portail : chemin de
            // fichier simple, pas un préfixe.
            $this->disk()->delete($prefix);

            return;
        }

        $this->disk()->deleteDirectory($prefix);
    }

    private function driver(): string
    {
        return extension_loaded('gd')
            ? Driver::class
            : \Intervention\Image\Drivers\Imagick\Driver::class;
    }

    private function encoder(): EncoderInterface
    {
        return $this->supportsWebp()
            ? new WebpEncoder(quality: 82)
            : new JpegEncoder(quality: 85, progressive: true);
    }

    private function extension(): string
    {
        return $this->supportsWebp() ? 'webp' : 'jpg';
    }

    /** GD compilé sans WebP (rare, mais possible selon l'image Docker) → JPEG. */
    private function supportsWebp(): bool
    {
        if (! extension_loaded('gd')) {
            return true; // Imagick : WebP quasi systématiquement disponible.
        }

        return (bool) (gd_info()['WebP Support'] ?? false);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

/**
 * R-11 — aucun test ne doit écrire dans le stockage de dev : un PDF de test
 * émis sous le numéro EW-2026-00001 écrasait celui de la base de démo.
 */
it('isole les disques local et public dans un répertoire de test', function (string $disk) {
    expect(Storage::disk($disk)->path(''))
        ->toContain(storage_path('framework/testing/disks'));
})->with(['local', 'public']);

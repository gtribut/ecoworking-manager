<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * C12.1 — Serving de la SPA portail en production (BRIEF §6, ADR-0004).
 *
 * La SPA est un projet Vite autonome (portal-spa/) buildé vers public/portal/
 * (base `/portal/`). Cette action catch-all sert le shell HTML Blade en
 * injectant les assets hashés depuis le manifest Vite — jamais de nom de
 * fichier codé en dur. Le routing applicatif est ensuite géré côté client
 * (React Router).
 */
final class PortalSpaController extends Controller
{
    /** Manifest généré par `vite build` (option `manifest: true`). */
    private const string MANIFEST_PATH = 'portal/.vite/manifest.json';

    /** Clé d'entrée du manifest (l'entrée Vite de la SPA est index.html). */
    private const string ENTRY_KEY = 'index.html';

    public function __invoke(): View
    {
        $manifestPath = public_path(self::MANIFEST_PATH);

        if (! is_file($manifestPath)) {
            // Build absent = erreur de déploiement (le build est une étape du
            // déploiement, les assets ne sont pas commités). 503 explicite
            // plutôt qu'une page blanche silencieuse.
            abort(503, 'Build de la SPA introuvable (manifest Vite absent).');
        }

        /** @var array<string, array{file?: string, css?: list<string>, imports?: list<string>}>|null $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = $manifest[self::ENTRY_KEY] ?? null;

        if (! is_array($entry) || ! isset($entry['file'])) {
            abort(503, 'Manifest Vite invalide (entrée index.html absente).');
        }

        // Chunks importés statiquement par l'entrée : préchargés (modulepreload)
        // pour éviter la cascade de découverte au premier rendu.
        $preloads = [];

        foreach ($entry['imports'] ?? [] as $import) {
            if (isset($manifest[$import]['file'])) {
                $preloads[] = $this->assetUrl($manifest[$import]['file']);
            }
        }

        return view('portal-spa', [
            'js' => $this->assetUrl($entry['file']),
            'css' => array_map($this->assetUrl(...), $entry['css'] ?? []),
            'preloads' => $preloads,
        ]);
    }

    private function assetUrl(string $file): string
    {
        return '/portal/'.$file;
    }
}

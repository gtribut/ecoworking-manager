<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * C12.1 — Serving de la SPA portail (BRIEF §6, ADR-0004).
 *
 * La route catch-all du domaine portail sert le shell HTML Blade avec les
 * assets hashés injectés depuis le manifest Vite (public/portal/.vite/
 * manifest.json). Un manifest factice est posé pour les tests (le build réel
 * n'est pas commité) ; l'éventuel manifest d'un build local est restauré.
 */
beforeEach(function () {
    $this->manifestPath = public_path('portal/.vite/manifest.json');
    $this->originalManifest = File::exists($this->manifestPath)
        ? File::get($this->manifestPath)
        : null;

    File::ensureDirectoryExists(dirname($this->manifestPath));
    File::put($this->manifestPath, json_encode([
        'index.html' => [
            'file' => 'assets/index-TESTHASH.js',
            'css' => ['assets/index-TESTHASH.css'],
            'imports' => ['_vendor-TESTHASH.js'],
        ],
        '_vendor-TESTHASH.js' => ['file' => 'assets/vendor-TESTHASH.js'],
    ], JSON_THROW_ON_ERROR));
});

afterEach(function () {
    if ($this->originalManifest !== null) {
        File::put($this->manifestPath, $this->originalManifest);
    } else {
        File::delete($this->manifestPath);
    }
});

it('sert le shell HTML de la SPA à la racine avec les assets du manifest Vite', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<div id="root"></div>', false)
        ->assertSee('/portal/assets/index-TESTHASH.js', false)
        ->assertSee('/portal/assets/index-TESTHASH.css', false)
        ->assertSee('/portal/assets/vendor-TESTHASH.js', false);
});

it('sert le shell SPA sur les sous-routes client (deep links React Router)', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="root"></div>', false)
        ->assertSee('/portal/assets/index-TESTHASH.js', false);
})->with(['/factures', '/reservations/42', '/login']);

it("n'intercepte pas /api/* : 401 JSON et non le shell SPA", function () {
    $this->getJson('/api/user')
        ->assertUnauthorized()
        ->assertJsonStructure(['message'])
        ->assertDontSee('id="root"', false);

    // Même une navigation navigateur (sans en-tête Accept JSON) sur /api/*
    // reste du JSON (shouldRenderJsonWhen), jamais le HTML de la SPA.
    $this->get('/api/user')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertDontSee('id="root"', false);
});

it("renvoie un 404 JSON (pas le shell SPA) pour une route d'API inconnue", function () {
    $this->getJson('/api/route-inexistante')
        ->assertNotFound()
        ->assertDontSee('id="root"', false);
});

it("n'intercepte pas /sanctum/csrf-cookie : le flux CSRF Sanctum SPA reste fonctionnel", function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

it("n'intercepte pas le healthcheck /up", function () {
    $this->get('/up')
        ->assertOk()
        ->assertDontSee('id="root"', false);
});

it('renvoie un 503 explicite si le build SPA est absent (manifest Vite manquant)', function () {
    File::delete($this->manifestPath);

    $this->get('/factures')->assertServiceUnavailable();
});

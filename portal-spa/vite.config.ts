/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Portail SPA — servi sur portail.ecoworking.fr (même origine que l'API en prod,
// donc pas de CORS). En dev, Vite proxifie les routes Laravel (Sanctum SPA :
// cookies de session + CSRF) vers le backend Sail. Cf. ADR-0003 / ADR-0006.
const apiProxyTarget = process.env.VITE_API_PROXY ?? 'http://localhost'

const proxiedPaths = [
  '/api',
  '/login',
  '/logout',
  '/sanctum',
  '/two-factor-challenge',
  '/magic-link',
  '/forgot-password',
  '/reset-password',
  '/user', // Fortify : mot de passe, 2FA (activation, QR, codes de récupération)
]

// Les pages de la SPA partagent certains chemins avec les POST Fortify
// (`/reset-password/:token` est une page, `POST /reset-password` une API) :
// seules les requêtes non-GET partent vers Laravel, les GET restent servis par Vite.
const spaPagePaths = ['/reset-password']

export default defineConfig(({ command }) => ({
  // En build les assets sont servis sous portail.ecoworking.fr/portal/*
  // (BRIEF §6). En dev le serveur Vite reste servi à la racine (:5173).
  base: command === 'build' ? '/portal/' : '/',
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: Object.fromEntries(
      proxiedPaths.map((path) => [
        path,
        {
          target: apiProxyTarget,
          changeOrigin: true,
          bypass: spaPagePaths.includes(path)
            ? (req: { method?: string; url?: string }) =>
                req.method === 'GET' ? req.url : undefined
            : undefined,
        },
      ]),
    ),
  },
  build: {
    // Build directement dans public/portal/ du projet Laravel (BRIEF §6,
    // ADR-0004) : la vue Blade `portal-spa` lit le manifest Vite
    // (public/portal/.vite/manifest.json) pour injecter les assets hashés.
    // Dossier gitignoré — buildé en CI / au déploiement.
    outDir: '../public/portal',
    emptyOutDir: true,
    manifest: true,
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
    // Les specs Playwright (e2e/*.spec.ts) ne sont PAS des tests Vitest :
    // restreint la découverte au code source (cf. docs/testing-e2e.md).
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
  },
}))

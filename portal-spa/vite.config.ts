/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Portail SPA — servi sur portail.ecoworking.fr (même origine que l'API en prod,
// donc pas de CORS). En dev, Vite proxifie les routes Laravel (Sanctum SPA :
// cookies de session + CSRF) vers le backend Sail. Cf. ADR-0003 / ADR-0006.
const apiProxyTarget = process.env.VITE_API_PROXY ?? 'http://localhost'

const proxiedPaths = ['/api', '/login', '/logout', '/sanctum', '/two-factor-challenge']

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: Object.fromEntries(
      proxiedPaths.map((path) => [path, { target: apiProxyTarget, changeOrigin: true }]),
    ),
  },
  build: {
    // Exposé par Laravel via public/portal/ (cf. CLAUDE.md arborescence).
    outDir: 'dist',
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
  },
})

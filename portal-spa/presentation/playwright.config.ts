import { defineConfig, devices } from '@playwright/test'

/**
 * Captures d'écran pour docs/presentation/ (pas une suite de tests).
 *
 * Le webServer (presentation/serve.sh) reseed la base DÉDIÉE `presentation`
 * avec DemoSeeder puis sert l'app comme en prod (SPA buildée + admin /admin).
 * Lancer via scripts/presentation/capture.sh (hôte) — le runner tourne dans le
 * conteneur Sail. PRESENTATION_REUSE=1 réutilise un serveur déjà lancé.
 */

const port = Number(process.env.PRESENTATION_PORT ?? 8092)
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: '.',
  testMatch: /\.spec\.ts$/,
  outputDir: '/tmp/presentation-results',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  timeout: 90_000,
  use: {
    baseURL,
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    colorScheme: 'light',
    ...devices['Desktop Chrome'],
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
  },
  webServer: {
    command: 'bash presentation/serve.sh',
    url: `${baseURL}/up`,
    reuseExistingServer: !!process.env.PRESENTATION_REUSE,
    timeout: 240_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
})

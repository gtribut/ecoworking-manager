import { defineConfig, devices } from '@playwright/test'

/**
 * Suite e2e SPA membre (C11.3, ADR-0008) — cf. docs/testing-e2e.md.
 *
 * Cible la SPA telle que servie EN PRODUCTION : build Vite dans public/portal/
 * + catch-all Laravel (C12.1). Le webServer (e2e/serve.sh) migre + seed la
 * base DÉDIÉE `e2e` (E2eSeeder) puis lance `php artisan serve` avec les
 * domaines vides (tout sur un seul host, comme phpunit.xml).
 *
 * Lancer via scripts/e2e/spa.sh (hôte) — le runner tourne dans le conteneur
 * Sail. En CI, tout tourne directement sur le runner.
 */

const port = Number(process.env.E2E_PORT ?? 8091)
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  // Suite stateful (seed partagé par run) : exécution séquentielle,
  // déterministe. La suite est petite, le coût est faible.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
  timeout: 30_000,
  use: {
    baseURL,
    trace: 'retain-on-failure',
    // Le portail est francophone et l'app vit en Europe/Paris (ADR-0010).
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'bash e2e/serve.sh',
    url: `${baseURL}/up`,
    // Jamais de réutilisation : chaque run repart d'un seed frais (migrate:fresh).
    reuseExistingServer: false,
    timeout: 180_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
})

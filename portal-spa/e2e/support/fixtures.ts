import { test as base } from '@playwright/test'

/**
 * Fixtures partagées de la suite e2e SPA.
 *
 * `checkA11y` est un POINT D'ANCRAGE pour C11.4 (audit axe-core) : les specs
 * l'appellent déjà sur leurs écrans critiques, mais l'implémentation est vide.
 * Pour activer l'audit : installer @axe-core/playwright puis remplacer le
 * corps de la fixture par une analyse AxeBuilder (fail si violations
 * serious/critical) — aucun spec à modifier.
 */
interface A11yFixtures {
  checkA11y: (context?: string) => Promise<void>
}

export const test = base.extend<A11yFixtures>({
  checkA11y: async ({ page: _page }, use) => {
    await use(async (_context?: string) => {
      // C11.4 : brancher axe-core ici (AxeBuilder({ page })...analyze()).
    })
  },
})

export { expect } from '@playwright/test'

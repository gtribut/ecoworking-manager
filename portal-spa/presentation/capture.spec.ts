import { expect, type Page, test } from '@playwright/test'
import { totp } from './totp'

/**
 * Captures d'écran de docs/presentation/ (portail membre + back-office).
 *
 * Pas une suite de tests : chaque `test` est un parcours de captures, sur la
 * base `presentation` seedée par DemoSeeder (comptes de docs/recette.md §1).
 * Sortie : docs/presentation/screenshots/{portail,admin}/*.png (versionnées).
 */

const OUT = '../docs/presentation/screenshots'
const PASSWORD = 'demo-password'
const CLAIRE = 'claire.fontaine@atelier-lumiere.demo'
const THOMAS = 'thomas.bernard@demo.fr'
const ADMIN = { email: 'admin@ecoworking.fr', password: 'presentation-admin' }

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle')
  // Transitions CSS / rendu FullCalendar.
  await page.waitForTimeout(600)
}

async function shot(page: Page, name: string, fullPage = true): Promise<void> {
  await settle(page)
  if (fullPage) {
    // Les blocs chargés au défilement (RelationManagers Filament) doivent être
    // rendus avant la capture pleine page.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    await settle(page)
    await page.evaluate(() => window.scrollTo(0, 0))
    await page.waitForTimeout(200)
  }
  await page.screenshot({ path: `${OUT}/${name}.png`, fullPage, animations: 'disabled' })
}

async function portalLogin(page: Page, email: string): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Mot de passe').fill(PASSWORD)
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()
}

async function portalPage(page: Page, path: string, name: string, fullPage = true): Promise<void> {
  await page.goto(path)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await shot(page, name, fullPage)
}

async function adminLogin(page: Page): Promise<void> {
  await page.goto('/admin/login')
  await page.getByLabel(/adresse e-mail/i).fill(ADMIN.email)
  await page.getByRole('textbox', { name: /mot de passe/i }).fill(ADMIN.password)
  await page.getByRole('button', { name: /^Connexion|Se connecter/ }).click()
  await page.getByLabel(/code à 6 chiffres/i).fill(totp())
  await page.getByRole('button', { name: 'Confirmer la connexion' }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Tableau de bord' })).toBeVisible()
}

test.describe('Portail membre — desktop', () => {
  test('parcours résidente (Claire)', async ({ page }) => {
    await page.goto('/login')
    await shot(page, 'portail/01-login', false)
    await portalLogin(page, CLAIRE)

    await shot(page, 'portail/02-dashboard')
    await portalPage(page, '/bookings', 'portail/03-reservations')
    await portalPage(page, '/invoices', 'portail/04-factures')
    await portalPage(page, '/documents', 'portail/05-documents')
    await portalPage(page, '/directory', 'portail/06-annuaire')
    await portalPage(page, '/directory/plan', 'portail/07-plan-etages')
    await portalPage(page, '/presence', 'portail/08-presence')
    await portalPage(page, '/announcements', 'portail/09-annonces')
    await portalPage(page, '/profile', 'portail/10-profil')
  })

  test('parcours nomade (Thomas) — tickets', async ({ page }) => {
    await portalLogin(page, THOMAS)
    await portalPage(page, '/tickets', 'portail/11-tickets')
    await portalPage(page, '/', 'portail/12-dashboard-nomade')
  })
})

test.describe('Portail membre — mobile', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
  })

  test('écrans clés en mobile (Claire)', async ({ page }) => {
    await portalLogin(page, CLAIRE)
    await shot(page, 'portail/m1-dashboard-mobile', false)
    await portalPage(page, '/bookings', 'portail/m2-reservations-mobile', false)
    await portalPage(page, '/invoices', 'portail/m3-factures-mobile', false)
  })
})

test.describe('Back-office admin', () => {
  test('tour des écrans clés', async ({ page }) => {
    await adminLogin(page)
    await shot(page, 'admin/01-dashboard')

    const adminPage = async (path: string, name: string): Promise<void> => {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      await shot(page, name)
    }

    await adminPage('/admin/daily-occupancy', 'admin/02-occupation-du-jour')
    await adminPage('/admin/bookings', 'admin/03-reservations')
    await adminPage('/admin/users', 'admin/04-comptes')
    await adminPage('/admin/companies', 'admin/05-entites')
    await page
      .getByRole('link', { name: /Atelier Lumière/ })
      .first()
      .click()
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    await shot(page, 'admin/06-entite-detail')
    await adminPage('/admin/offers', 'admin/07-catalogue')
    await adminPage('/admin/subscriptions', 'admin/08-abonnements')
    await adminPage('/admin/invoices', 'admin/09-factures')
    await page.getByRole('link', { name: 'EW-2026-00004' }).first().click()
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    await shot(page, 'admin/10-facture-detail')
    await adminPage('/admin/payments', 'admin/11-paiements')
    await adminPage('/admin/announcements', 'admin/12-annonces')
    await adminPage('/admin/administrative-documents', 'admin/13-documents-entites')
    await adminPage('/admin/activity-log/activities', 'admin/14-audit-log')
    await adminPage('/admin/role-permission-matrix', 'admin/15-roles-permissions')
  })
})

import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

/**
 * Passages a11y minimaux (C11.4) sur les écrans critiques SANS spec
 * fonctionnelle dédiée : naviguer + audit axe-core, rien de plus (les
 * comportements sont couverts par les tests Vitest des composants).
 *
 * Le thème sombre est également audité ici (contrastes AA dans les deux
 * thèmes, CLAUDE.md §3.5) : l'utilisateur e2e n'a pas de préférence de thème
 * (`theme` NULL) → la SPA suit `prefers-color-scheme`, que Playwright émule
 * via `colorScheme`.
 */

test.describe('Audit a11y — écrans sans spec dédiée', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
  })

  test('présence (résident)', async ({ page, checkA11y }) => {
    await page.goto('/presence')
    await expect(page.getByRole('heading', { level: 1, name: 'Ma présence' })).toBeVisible()
    // Bureau attitré du membre e2e (E2eSeeder : `desk-2`).
    await expect(page.getByText(/Votre bureau attitré/)).toBeVisible()
    // Le formulaire est révélé par le bouton (lot C) : on l'ouvre pour que
    // l'audit couvre aussi ses champs.
    await page.getByRole('button', { name: 'Marquer une absence' }).click()
    await expect(page.getByLabel('Date de début')).toBeVisible()
    await checkA11y('presence')
  })

  test('profil', async ({ page, checkA11y }) => {
    await page.goto('/profile')
    await expect(page.getByRole('heading', { level: 1, name: 'Mon profil' })).toBeVisible()
    await expect(page.getByRole('textbox', { name: 'Email' })).toBeVisible()
    await checkA11y('profile')
  })

  test('centre de notifications (panneau ouvert)', async ({ page, checkA11y }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

    // Le seed génère de vraies notifications (facture émise, annonces).
    await page.getByRole('button', { name: /^Notifications/ }).click()
    const panel = page.getByRole('region', { name: 'Notifications' })
    await expect(panel).toBeVisible()
    await expect(panel.getByRole('listitem').first()).toBeVisible()
    await checkA11y('notifications')
  })
})

/**
 * « Tickets & bureaux nomades » : route gardée par
 * <RequireAccess permission="create-paid-booking">, que seul le rôle
 * `external` porte — d'où un describe à part, connecté au compte nomade
 * plutôt qu'au résident des tests ci-dessus.
 */
test.describe('Audit a11y — tickets & bureaux nomades (external)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page, seed.external.email)
  })

  test('tickets & bureaux nomades', async ({ page, checkA11y }) => {
    await page.goto('/tickets')
    await expect(
      page.getByRole('heading', { level: 1, name: 'Tickets & bureaux nomades' }),
    ).toBeVisible()

    // Soldes crédités par E2eSeeder : l'audit porte sur une page remplie, pas
    // sur un état vide (les états vides ont leur propre rendu).
    const balances = page.getByRole('region', { name: 'Mes soldes de tickets' })
    await expect(balances.getByText(String(seed.external.deskTickets))).toBeVisible()
    await expect(balances.getByText(String(seed.external.roomTickets))).toBeVisible()

    await checkA11y('tickets')
  })
})

test.describe('Audit a11y — thème sombre', () => {
  // `theme` NULL côté user → la classe `dark` suit prefers-color-scheme.
  test.use({ colorScheme: 'dark' })

  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
  })

  test('dashboard en thème sombre', async ({ page, checkA11y }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Actualités Ecoworking' })).toBeVisible()
    await expect(page.locator('html.dark')).toBeAttached()
    await checkA11y('dashboard-dark')
  })

  test('réservations (page dense) en thème sombre', async ({ page, checkA11y }) => {
    await page.goto('/bookings')
    await expect(
      page.getByRole('heading', { level: 1, name: 'Réservations de salles' }),
    ).toBeVisible()

    // Grille du calendrier affichée : c'est la partie dense/riche de l'écran.
    await expect(page.getByRole('heading', { name: 'Calendrier des salles' })).toBeVisible()
    await expect(page.getByRole('table').first()).toBeVisible()
    await expect(page.getByRole('heading', { name: /Vue liste/ })).toBeVisible()
    await expect(page.locator('html.dark')).toBeAttached()
    await checkA11y('bookings-dark')
  })
})

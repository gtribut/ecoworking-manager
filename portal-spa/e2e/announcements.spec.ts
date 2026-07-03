import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Annonces & événements (C12.3)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
  })

  test('liste et détail', async ({ page, checkA11y }) => {
    await page.goto('/announcements')

    await expect(
      page.getByRole('heading', { level: 1, name: 'Actualités Ecoworking' }),
    ).toBeVisible()
    await expect(page.getByRole('heading', { name: seed.announcement.news })).toBeVisible()
    await expect(page.getByRole('heading', { name: seed.announcement.event })).toBeVisible()
    await checkA11y('announcements')

    await page.getByRole('link', { name: seed.announcement.event, exact: true }).click()
    await expect(
      page.getByRole('heading', { level: 1, name: seed.announcement.event }),
    ).toBeVisible()
    await expect(page.getByText('Cuisine du 1er étage')).toBeVisible()

    await checkA11y('announcement-detail')
  })

  test('RSVP : inscription puis désinscription', async ({ page }) => {
    await page.goto('/announcements')
    await page.getByRole('link', { name: seed.announcement.event, exact: true }).click()
    await expect(
      page.getByRole('heading', { level: 1, name: seed.announcement.event }),
    ).toBeVisible()

    // Inscription.
    await page.getByRole('button', { name: 'M’inscrire à l’événement' }).click()
    await expect(page.getByText('Vous êtes inscrit(e) à cet événement.')).toBeVisible()

    // Désinscription (confirmation accessible en deux temps).
    await page.getByRole('button', { name: 'Me désinscrire' }).click()
    await expect(page.getByText('Annuler votre inscription ?')).toBeVisible()
    await page.getByRole('button', { name: 'Confirmer' }).click()

    await expect(page.getByRole('button', { name: 'M’inscrire à l’événement' })).toBeVisible()
  })
})

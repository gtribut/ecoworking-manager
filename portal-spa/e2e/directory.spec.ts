import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Annuaire + plan des étages (C12.5)', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
  })

  test('annuaire : liste opt-in uniquement', async ({ page, checkA11y }) => {
    await page.goto('/directory')

    await expect(
      page.getByRole('heading', { level: 1, name: 'Annuaire des coworkers' }),
    ).toBeVisible()

    // Opt-in visible, opt-out invisible (isolation du consentement).
    await expect(
      page.getByRole('heading', {
        name: `${seed.member.firstName} ${seed.member.lastName}`,
      }),
    ).toBeVisible()
    await expect(page.getByText('Designer produit')).toBeVisible()
    await expect(page.getByText(seed.other.lastName)).toHaveCount(0)

    await checkA11y('directory')
  })

  test('plan des étages : SVG interactif au clavier + alternative texte', async ({
    page,
    checkA11y,
  }) => {
    await page.goto('/directory')
    await page.getByRole('link', { name: 'Plan des étages' }).click()

    await expect(page.getByRole('heading', { level: 1, name: 'Plan des étages' })).toBeVisible()

    // Le SVG est exposé en groupe nommé, chaque bureau est un bouton nommé.
    await expect(page.getByRole('group', { name: "Plan de l'étage 1" })).toBeVisible()
    const desk = page.getByRole('button', { name: /^Bureau 1 — / })
    await expect(desk).toBeVisible()

    // Interaction 100 % clavier : focus + Entrée ouvrent le détail, Échap ferme.
    await desk.focus()
    await page.keyboard.press('Enter')
    await expect(page.getByRole('heading', { name: 'Bureau 1', exact: true })).toBeVisible()
    await expect(page.getByText('Bureau libre — pour réserver ce type de bureau')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('heading', { name: 'Bureau 1', exact: true })).toBeHidden()

    // Alternative texte synchronisée (RGAA) : mêmes libellés que le SVG.
    await expect(page.getByRole('heading', { name: 'Occupation en liste' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Étage 1', exact: true })).toBeVisible()
    await expect(page.locator('#plan-alternative').getByText(/^Bureau 1 — /)).toBeVisible()

    await checkA11y('floor-plan')
  })
})

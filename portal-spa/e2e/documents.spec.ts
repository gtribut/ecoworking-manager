import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Documents internes (C12.4)', () => {
  test('valider un document interne', async ({ page, checkA11y }) => {
    await loginViaApi(page)
    await page.goto('/documents')

    await expect(page.getByRole('heading', { level: 1, name: 'Documents' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Documents à valider' })).toBeVisible()
    await expect(
      page.getByText(`${seed.internalDocument.title} (version ${seed.internalDocument.version})`),
    ).toBeVisible()

    // Validation avec confirmation accessible en deux temps. Le titre du
    // document est en sr-only dans le nom accessible (review U5 : distingue
    // les boutons homonymes « Valider » sur la même page).
    await page
      .getByRole('button', { name: `Valider le document ${seed.internalDocument.title}` })
      .click()
    await expect(
      page.getByText(`Valider « ${seed.internalDocument.title} » ?`, { exact: false }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Confirmer' }).click()

    // Le document passe dans « déjà validés », plus rien à valider.
    await expect(page.getByText('Tous vos documents sont à jour.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Documents déjà validés' })).toBeVisible()
    await expect(page.getByText(/Validé le/)).toBeVisible()

    await checkA11y('documents')
  })
})

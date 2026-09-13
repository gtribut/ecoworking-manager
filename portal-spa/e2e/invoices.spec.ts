import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Mes factures', () => {
  test('liste + téléchargement du PDF', async ({ page, checkA11y }) => {
    await loginViaApi(page)
    await page.goto('/invoices')

    await expect(page.getByRole('heading', { level: 1, name: 'Mes factures' })).toBeVisible()
    await expect(page.getByRole('rowheader', { name: seed.invoice.number })).toBeVisible()
    await expect(page.getByText('En attente')).toBeVisible()

    // Téléchargement réel du PDF généré à l'émission (flux argent critique).
    const downloadPromise = page.waitForEvent('download')
    await page.getByRole('link', { name: `Télécharger la facture ${seed.invoice.number}` }).click()
    const download = await downloadPromise
    expect(download.suggestedFilename()).toBe(`${seed.invoice.number}.pdf`)

    await checkA11y('invoices')
  })

  test('filtres, tri et bloc entité (PRD §3.6.2 / §3.6.4)', async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/invoices')

    const row = page.getByRole('rowheader', { name: seed.invoice.number })
    await expect(row).toBeVisible()

    // Bloc « Mon entreprise » du module administratif.
    await expect(page.getByRole('heading', { level: 2, name: 'Mon entreprise' })).toBeVisible()
    await expect(page.getByText(seed.entity.legalName)).toBeVisible()

    // Tri par numéro : l'en-tête porte l'état via aria-sort.
    await page.getByRole('button', { name: /Numéro/ }).click()
    await expect(page.getByRole('columnheader', { name: /Numéro/ })).toHaveAttribute(
      'aria-sort',
      'ascending',
    )
    await expect(page).toHaveURL(/sort=number/)

    // Filtre année : la facture e2e est émise cette année.
    await page.getByLabel('Année').selectOption(String(new Date().getFullYear()))
    await expect(row).toBeVisible()

    // Filtre statut non concordant : état vide filtré + réinitialisation.
    await page.getByLabel('Statut').selectOption('paid')
    await expect(page.getByText('Aucune facture ne correspond à ces filtres.')).toBeVisible()
    await page.getByRole('button', { name: 'Réinitialiser les filtres' }).first().click()
    await expect(row).toBeVisible()
  })
})

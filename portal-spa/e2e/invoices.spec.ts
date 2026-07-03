import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Mes factures', () => {
  test('liste + téléchargement du PDF', async ({ page, checkA11y }) => {
    await loginViaApi(page)
    await page.goto('/invoices')

    await expect(page.getByRole('heading', { level: 1, name: 'Mes factures' })).toBeVisible()
    await expect(page.getByRole('rowheader', { name: seed.invoice.number })).toBeVisible()
    await expect(page.getByText('Émise')).toBeVisible()

    // Téléchargement réel du PDF généré à l'émission (flux argent critique).
    const downloadPromise = page.waitForEvent('download')
    await page.getByRole('link', { name: `Télécharger la facture ${seed.invoice.number}` }).click()
    const download = await downloadPromise
    expect(download.suggestedFilename()).toBe(`${seed.invoice.number}.pdf`)

    await checkA11y('invoices')
  })
})

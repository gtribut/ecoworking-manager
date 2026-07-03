import { expect, test } from './support/fixtures'
import { extractLink, fetchLatestMessageFor } from './support/mailpit'
import { seed } from './support/seed'

test.describe('Magic link (C12.8a)', () => {
  test('demande → email Mailpit → consommation → connecté', async ({ page, checkA11y }) => {
    // Marge de 5 s : tolère un léger décalage d'horloge entre conteneurs.
    const requestedAt = new Date(Date.now() - 5_000)

    await page.goto('/login')
    await page.getByRole('button', { name: 'Recevoir un lien de connexion par email' }).click()
    await checkA11y('login-magic-link')
    await page.getByLabel('Email').fill(seed.member.email)
    await page.getByRole('button', { name: 'Recevoir le lien de connexion' }).click()

    // Réponse générique anti-énumération, affichée dans tous les cas.
    await expect(
      page.getByText(/Si un compte correspond à cette adresse, un lien de connexion/),
    ).toBeVisible()

    // L'email part en synchrone (QUEUE_CONNECTION=sync) vers Mailpit.
    const message = await fetchLatestMessageFor(page.request, seed.member.email, {
      subject: 'Votre lien de connexion',
      newerThan: requestedAt,
    })
    const link = extractLink(message.html, '/magic-link/')

    // Consommation : URL signée à usage unique → session ouverte → dashboard.
    await page.goto(link)
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

    // Rejeu du même lien : usage unique → retour login avec message d'erreur.
    await page.context().clearCookies()
    await page.goto(link)
    await expect(
      page.getByText('Ce lien de connexion est invalide, déjà utilisé ou expiré', {
        exact: false,
      }),
    ).toBeVisible()
  })
})

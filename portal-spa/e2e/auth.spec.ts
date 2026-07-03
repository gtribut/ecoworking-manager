import { expireSessionServerSide, loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test.describe('Session membre', () => {
  test('login mot de passe → dashboard (annonces + documents)', async ({ page, checkA11y }) => {
    await page.goto('/login')
    await expect(page.getByRole('heading', { level: 1, name: 'Portail Ecoworking' })).toBeVisible()
    await checkA11y('login')

    await page.getByLabel('Email').fill(seed.member.email)
    await page.getByLabel('Mot de passe').fill(seed.password)
    await page.getByRole('button', { name: 'Se connecter' }).click()

    // Dashboard : accueil personnalisé + blocs C12.3 (annonces) et C12.4 (documents).
    await expect(
      page.getByRole('heading', { level: 1, name: new RegExp(`Bonjour ${seed.member.firstName}`) }),
    ).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Actualités Ecoworking' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Documents à valider' })).toBeVisible()
    await expect(page.getByText(seed.announcement.news, { exact: true })).toBeVisible()

    await checkA11y('dashboard')
  })

  test('identifiants invalides → message d’erreur, pas de session', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill(seed.member.email)
    await page.getByLabel('Mot de passe').fill('mauvais-mot-de-passe')
    await page.getByRole('button', { name: 'Se connecter' }).click()

    await expect(
      page.getByText('Ces identifiants ne correspondent pas à nos enregistrements.'),
    ).toBeVisible()
    await expect(page).toHaveURL(/\/login/)
  })

  test('logout → retour au login', async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

    await page.getByRole('button', { name: 'Déconnexion' }).click()

    await expect(page).toHaveURL(/\/login/)
    await expect(page.getByRole('button', { name: 'Se connecter' })).toBeVisible()
  })

  test('accès non authentifié → redirection login', async ({ page }) => {
    await page.goto('/invoices')

    await expect(page).toHaveURL(/\/login/)
    await expect(page.getByRole('heading', { level: 1, name: 'Portail Ecoworking' })).toBeVisible()
  })

  test('session expirée (401 en cours de navigation) → redirection login', async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

    // La session meurt côté serveur : la prochaine requête API répond 401 →
    // l'intercepteur purge l'état auth → RequireAuth redirige vers /login.
    await expireSessionServerSide(page)
    await page.getByRole('link', { name: 'Factures', exact: true }).click()

    await expect(page).toHaveURL(/\/login/)
  })
})

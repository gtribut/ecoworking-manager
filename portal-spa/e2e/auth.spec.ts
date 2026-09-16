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

    // La déconnexion vit désormais dans le menu profil (PRD §3.2, lot G).
    await page.getByRole('button', { name: new RegExp(seed.member.firstName) }).click()
    await page.getByRole('menuitem', { name: 'Déconnexion' }).click()

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

    // Course inévitable : après l'expiration, la première requête API qui part
    // — celle d'une requête de fond TanStack Query aussi bien que celle
    // provoquée par le clic — reçoit 401, l'intercepteur purge l'état auth et
    // <RequireAuth> redirige. Le lien « Factures » est alors détaché du DOM
    // pendant que Playwright l'attend, et `click()` expire au bout de 30 s
    // (échec observé sur `main`). On attend donc la redirection et le clic en
    // parallèle : quelle que soit la requête qui déclenche le 401, la
    // vérification reste la même — session morte = retour au login.
    const invoicesLink = page.getByRole('link', { name: 'Factures', exact: true })
    await Promise.all([
      expect(page).toHaveURL(/\/login/, { timeout: 15_000 }),
      invoicesLink.click({ noWaitAfter: true, timeout: 15_000 }).catch(() => undefined),
    ])

    await expect(page.getByRole('heading', { level: 1, name: 'Portail Ecoworking' })).toBeVisible()
  })
})

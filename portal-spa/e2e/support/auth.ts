import { type Cookie, expect, type Page } from '@playwright/test'
import { seed } from './seed'

/**
 * Connexion Sanctum SPA par API (cookie de session posé dans le contexte du
 * navigateur via `page.request`, qui partage le cookie jar) : les specs hors
 * auth.spec.ts ne repaient pas le parcours de login UI à chaque test.
 *
 * Les cookies de session sont mis en cache par email (workers=1) : le
 * throttle Fortify (5 logins/min par email+IP) ne supporterait pas un vrai
 * login par test. Si la session cachée est morte (logout d'un test
 * précédent), on se reconnecte proprement.
 */
const sessionCache = new Map<string, Cookie[]>()

/** Origine servie par e2e/serve.sh — doit rester alignée avec playwright.config.ts. */
export const APP_ORIGIN = `http://127.0.0.1:${process.env.E2E_PORT ?? 8091}`

/**
 * En-têtes requis pour appeler /api/* via `page.request` : Sanctum ne
 * considère une requête « stateful » (session cookie honorée) que si son
 * Referer/Origin correspond aux domaines stateful — le navigateur les envoie
 * naturellement, l'API request de Playwright non.
 */
export function apiHeaders(extra: Record<string, string> = {}): Record<string, string> {
  return { Accept: 'application/json', Referer: `${APP_ORIGIN}/`, ...extra }
}

export async function loginViaApi(
  page: Page,
  email: string = seed.member.email,
  password: string = seed.password,
): Promise<void> {
  const cached = sessionCache.get(email)
  if (cached) {
    await page.context().addCookies(cached)
    const me = await page.request.get('/api/user', { headers: apiHeaders() })
    if (me.ok()) {
      return
    }
    sessionCache.delete(email)
    await page.context().clearCookies()
  }

  await page.request.get('/sanctum/csrf-cookie', { headers: apiHeaders() })
  const response = await page.request.post('/login', {
    headers: apiHeaders({ 'X-XSRF-TOKEN': await xsrfToken(page) }),
    data: { email, password },
  })
  expect(response.ok(), `login API ${email} → ${response.status()}`).toBeTruthy()
  sessionCache.set(email, await page.context().cookies())
}

/**
 * Tue la session CÔTÉ SERVEUR sans toucher à l'état client de la SPA —
 * simule une session expirée en cours de navigation. (Supprimer les cookies
 * ne suffit pas : Laravel re-émet le cookie de session sur chaque réponse
 * encore en vol, qui reste valide côté serveur.)
 */
export async function expireSessionServerSide(
  page: Page,
  email: string = seed.member.email,
): Promise<void> {
  const response = await page.request.post('/logout', {
    headers: apiHeaders({ 'X-XSRF-TOKEN': await xsrfToken(page) }),
  })
  expect(response.ok(), `logout API → ${response.status()}`).toBeTruthy()
  sessionCache.delete(email)
}

/** Jeton CSRF courant (cookie XSRF-TOKEN, URL-décodé) pour les POST hors axios. */
export async function xsrfToken(page: Page): Promise<string> {
  const cookies = await page.context().cookies()
  const cookie = cookies.find((candidate) => candidate.name === 'XSRF-TOKEN')
  if (!cookie) {
    throw new Error('Cookie XSRF-TOKEN absent — appeler /sanctum/csrf-cookie d’abord.')
  }
  return decodeURIComponent(cookie.value)
}

import type { InternalAxiosRequestConfig } from 'axios'
import axios, { isAxiosError } from 'axios'

/**
 * Client HTTP du portail (Sanctum mode SPA, ADR-0003).
 *
 * - `withCredentials` : envoie le cookie de session sur chaque requête.
 * - `withXSRFToken` : axios relit le cookie `XSRF-TOKEN` posé par
 *   `GET /sanctum/csrf-cookie` et le renvoie en en-tête `X-XSRF-TOKEN` sur les
 *   requêtes mutatives (POST/PATCH/DELETE) — protection CSRF.
 * - Même origine en prod (portail.ecoworking.fr) → `baseURL` relatif ; en dev
 *   le proxy Vite route vers le backend Sail.
 */
export const http = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Amorce le cookie CSRF avant toute requête mutative (login, etc.). */
export async function ensureCsrfCookie(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}

/**
 * Callback déclenché quand la session est manifestement expirée (401 hors flux
 * d'authentification, ou 419 non récupérable). Enregistré par l'AuthProvider :
 * il invalide la query auth, ce qui déclenche la redirection login (RequireAuth).
 */
let sessionExpiredHandler: (() => void) | null = null

export function setSessionExpiredHandler(handler: (() => void) | null): void {
  sessionExpiredHandler = handler
}

/**
 * Requêtes du flux d'authentification : un 401/419 y a un sens local (mauvais
 * identifiants, session déjà absente…) et est géré par l'appelant — on ne
 * déclenche pas la déconnexion globale pour elles.
 */
const AUTH_FLOW_URLS = ['/api/user', '/login', '/two-factor-challenge', '/logout', '/magic-link']

function isAuthFlowRequest(url: string | undefined): boolean {
  if (!url) return false
  return AUTH_FLOW_URLS.some((authUrl) => url === authUrl || url.endsWith(authUrl))
}

interface RetriableConfig extends InternalAxiosRequestConfig {
  /** Marqueur interne : la requête a déjà été rejouée après un 419. */
  _csrfRetried?: boolean
}

/**
 * Interceptor global de session expirée (finding review 05-#1) :
 * - 419 (CSRF token mismatch) : ré-amorce le cookie CSRF puis rejoue la requête
 *   UNE seule fois ; si elle échoue encore, la session est considérée expirée.
 * - 401 hors flux d'auth : session expirée → handler global (redirection login).
 * Aucun message brut « CSRF token mismatch » ne doit atteindre l'utilisateur.
 */
http.interceptors.response.use(undefined, async (error: unknown) => {
  if (!isAxiosError(error) || !error.response || !error.config) {
    return Promise.reject(error)
  }

  const status = error.response.status
  const config = error.config as RetriableConfig

  if (status === 419 && !config._csrfRetried) {
    config._csrfRetried = true
    try {
      await ensureCsrfCookie()
    } catch {
      // Impossible de ré-amorcer le cookie : session considérée expirée.
      if (!isAuthFlowRequest(config.url)) sessionExpiredHandler?.()
      return Promise.reject(error)
    }
    // Le rejeu repasse par cet interceptor : un nouveau 419 (ou un 401)
    // tombera dans la branche « session expirée » ci-dessous.
    return http.request(config)
  }

  if ((status === 401 || status === 419) && !isAuthFlowRequest(config.url)) {
    sessionExpiredHandler?.()
  }

  return Promise.reject(error)
})

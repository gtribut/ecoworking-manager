import { ensureCsrfCookie, http } from '@/lib/http'
import type { AuthUser, LoginCredentials } from './types'

export type LoginResult = { status: 'authenticated' } | { status: 'two-factor-required' }

/** Récupère l'utilisateur courant ; lève (401) si non authentifié. */
export async function fetchCurrentUser(): Promise<AuthUser> {
  const { data } = await http.get<AuthUser>('/api/user')
  return data
}

/**
 * Connexion Fortify (Sanctum SPA). Amorce d'abord le cookie CSRF, puis poste les
 * identifiants. Fortify renvoie `{ two_factor: true }` quand un second facteur
 * est requis (2FA membre activé) — on bascule alors sur le défi. Les erreurs de
 * validation (422) remontent telles quelles à l'appelant.
 */
export async function login(credentials: LoginCredentials): Promise<LoginResult> {
  await ensureCsrfCookie()
  const { data } = await http.post<{ two_factor?: boolean }>('/login', credentials)
  return data?.two_factor ? { status: 'two-factor-required' } : { status: 'authenticated' }
}

/** Valide le défi 2FA (code TOTP ou code de récupération). */
export async function twoFactorChallenge(payload: {
  code?: string
  recovery_code?: string
}): Promise<void> {
  await http.post('/two-factor-challenge', payload)
}

/**
 * Demande l'envoi d'un lien de connexion par email (magic link, PRD §3.2).
 * La réponse du back est volontairement générique, que l'email corresponde ou
 * non à un compte (anti-énumération) — ne rien déduire d'un 200.
 */
export async function requestMagicLink(email: string): Promise<void> {
  await ensureCsrfCookie()
  await http.post('/magic-link', { email })
}

export async function logout(): Promise<void> {
  await http.post('/logout')
}

import { isAxiosError } from 'axios'
import { http } from '@/lib/http'

/**
 * Endpoints Fortify de la 2FA TOTP membre (PRD §3.2 « optionnelle », recette
 * R-04). Fortify est configuré `confirmPassword: true` : les mutations 2FA
 * exigent une confirmation récente du mot de passe, signalée par un **423**
 * — l'appelant enchaîne alors `confirmPassword()` puis rejoue l'action.
 */

export const PASSWORD_CONFIRMATION_REQUIRED = 423

export function needsPasswordConfirmation(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === PASSWORD_CONFIRMATION_REQUIRED
}

export async function confirmPassword(password: string): Promise<void> {
  await http.post('/user/confirm-password', { password })
}

/** Génère le secret (2FA « en attente de confirmation », pas encore active). */
export async function enableTwoFactor(): Promise<void> {
  await http.post('/user/two-factor-authentication')
}

/** QR code SVG (à scanner) + URL otpauth, pour l'activation. */
export async function fetchTwoFactorQrCode(): Promise<{ svg: string; url: string }> {
  const { data } = await http.get<{ svg: string; url: string }>('/user/two-factor-qr-code')
  return data
}

/** Clé secrète en clair (saisie manuelle dans l'application). */
export async function fetchTwoFactorSecretKey(): Promise<string> {
  const { data } = await http.get<{ secretKey: string }>('/user/two-factor-secret-key')
  return data.secretKey
}

/** Confirme l'activation avec un premier code TOTP valide (422 sinon). */
export async function confirmTwoFactor(code: string): Promise<void> {
  await http.post('/user/confirmed-two-factor-authentication', { code })
}

export async function fetchRecoveryCodes(): Promise<string[]> {
  const { data } = await http.get<string[]>('/user/two-factor-recovery-codes')
  return data
}

export async function regenerateRecoveryCodes(): Promise<void> {
  await http.post('/user/two-factor-recovery-codes')
}

export async function disableTwoFactor(): Promise<void> {
  await http.delete('/user/two-factor-authentication')
}

import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'
import { getApiErrorMessage } from './errors'

function axiosErrorWith(status: number, data: unknown): AxiosError {
  const error = new AxiosError('Request failed')
  error.response = {
    status,
    data,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

describe('getApiErrorMessage', () => {
  it('retourne le message Laravel si présent', () => {
    expect(getApiErrorMessage(axiosErrorWith(409, { message: 'Conflit.' }))).toBe('Conflit.')
  })

  it('traduit un 401 en message d’identifiants', () => {
    expect(getApiErrorMessage(axiosErrorWith(401, {}))).toBe('Identifiants invalides.')
  })

  it('traduit un 419 en message de session expirée (jamais de « CSRF token mismatch »)', () => {
    expect(getApiErrorMessage(axiosErrorWith(419, { message: 'CSRF token mismatch.' }))).toBe(
      'Votre session a expiré. Veuillez vous reconnecter.',
    )
  })

  it('retombe sur le message par défaut hors Axios', () => {
    expect(getApiErrorMessage(new Error('x'), 'défaut')).toBe('défaut')
  })
})

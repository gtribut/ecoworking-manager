import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'
import { getApiErrorMessage, getValidationErrors } from './errors'

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

  it('retombe sur le message par défaut hors Axios', () => {
    expect(getApiErrorMessage(new Error('x'), 'défaut')).toBe('défaut')
  })
})

describe('getValidationErrors', () => {
  it('aplati les erreurs 422 au premier message par champ', () => {
    const errors = getValidationErrors(
      axiosErrorWith(422, { errors: { email: ['Email invalide.', 'autre'] } }),
    )
    expect(errors).toEqual({ email: 'Email invalide.' })
  })

  it('retourne un objet vide hors 422', () => {
    expect(getValidationErrors(axiosErrorWith(500, {}))).toEqual({})
  })
})

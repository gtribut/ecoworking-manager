import { HttpResponse, http as mswHttp } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import { http, setSessionExpiredHandler } from './http'

afterEach(() => {
  setSessionExpiredHandler(null)
})

describe('interceptor session expirée (401/419)', () => {
  it('rejoue une seule fois la requête en 419 après ré-amorçage du cookie CSRF', async () => {
    let attempts = 0
    let csrfCalled = false
    server.use(
      mswHttp.get('/sanctum/csrf-cookie', () => {
        csrfCalled = true
        return new HttpResponse(null, { status: 204 })
      }),
      mswHttp.post('/api/bookings', () => {
        attempts += 1
        if (attempts === 1) {
          return HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
        }
        return HttpResponse.json({ data: { id: 1 } }, { status: 201 })
      }),
    )
    const onExpired = vi.fn()
    setSessionExpiredHandler(onExpired)

    const response = await http.post('/api/bookings', {})

    expect(response.status).toBe(201)
    expect(attempts).toBe(2)
    expect(csrfCalled).toBe(true)
    expect(onExpired).not.toHaveBeenCalled()
  })

  it('déclenche la session expirée si le 419 persiste après le rejeu', async () => {
    let attempts = 0
    server.use(
      mswHttp.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
      mswHttp.post('/api/bookings', () => {
        attempts += 1
        return HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
      }),
    )
    const onExpired = vi.fn()
    setSessionExpiredHandler(onExpired)

    await expect(http.post('/api/bookings', {})).rejects.toMatchObject({
      response: { status: 419 },
    })
    expect(attempts).toBe(2)
    expect(onExpired).toHaveBeenCalledTimes(1)
  })

  it('déclenche la session expirée sur un 401 hors flux d’authentification', async () => {
    server.use(mswHttp.get('/api/tickets', () => new HttpResponse(null, { status: 401 })))
    const onExpired = vi.fn()
    setSessionExpiredHandler(onExpired)

    await expect(http.get('/api/tickets')).rejects.toMatchObject({ response: { status: 401 } })
    expect(onExpired).toHaveBeenCalledTimes(1)
  })

  it('ignore les 401 du flux d’authentification (/api/user, /login)', async () => {
    server.use(
      mswHttp.get('/api/user', () => new HttpResponse(null, { status: 401 })),
      mswHttp.post('/login', () => new HttpResponse(null, { status: 401 })),
    )
    const onExpired = vi.fn()
    setSessionExpiredHandler(onExpired)

    await expect(http.get('/api/user')).rejects.toMatchObject({ response: { status: 401 } })
    await expect(http.post('/login', {})).rejects.toMatchObject({ response: { status: 401 } })
    expect(onExpired).not.toHaveBeenCalled()
  })
})

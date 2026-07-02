import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { CalendarSubscription } from './CalendarSubscription'

const enabled = {
  enabled: true,
  urls: {
    mine: 'http://localhost/calendar/abc/mine.ics',
    entity: 'http://localhost/calendar/abc/entity.ics',
  },
}

describe('CalendarSubscription', () => {
  it('affiche les URLs de flux quand l’abonnement est actif', async () => {
    server.use(http.get('/api/calendar', () => HttpResponse.json(enabled)))

    renderWithProviders(<CalendarSubscription />)

    expect(await screen.findByDisplayValue(enabled.urls.mine)).toBeInTheDocument()
    expect(screen.getByDisplayValue(enabled.urls.entity)).toBeInTheDocument()
  })

  it('propose d’activer l’abonnement quand il est désactivé', async () => {
    server.use(http.get('/api/calendar', () => HttpResponse.json({ enabled: false, urls: null })))

    renderWithProviders(<CalendarSubscription />)

    expect(await screen.findByRole('button', { name: /activer l’abonnement/i })).toBeInTheDocument()
  })

  it('régénère les liens après confirmation', async () => {
    const user = userEvent.setup()
    let regenerated = false
    server.use(
      http.get('/api/calendar', () => HttpResponse.json(enabled)),
      http.post('/api/calendar/token', () => {
        regenerated = true
        return HttpResponse.json(enabled)
      }),
    )

    renderWithProviders(<CalendarSubscription />)

    await user.click(await screen.findByRole('button', { name: /régénérer les liens/i }))
    // Confirmation accessible avant l'action destructrice (les anciens liens sont invalidés).
    expect(regenerated).toBe(false)
    await user.click(await screen.findByRole('button', { name: /oui, régénérer/i }))

    await waitFor(() => expect(regenerated).toBe(true))
  })

  it('normalise la révocation (payload sans urls) en urls: null', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/calendar', () => HttpResponse.json(enabled)),
      // Le back peut renvoyer `{ enabled: false }` sans clé `urls` : la SPA
      // doit retomber sur l'état « abonnement désactivé » sans casser le cache.
      http.delete('/api/calendar/token', () => HttpResponse.json({ enabled: false })),
    )

    renderWithProviders(<CalendarSubscription />)

    await user.click(await screen.findByRole('button', { name: /désactiver l’abonnement/i }))
    await user.click(await screen.findByRole('button', { name: /oui, désactiver/i }))

    expect(await screen.findByRole('button', { name: /activer l’abonnement/i })).toBeInTheDocument()
  })
})

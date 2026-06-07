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

  it('régénère les liens', async () => {
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

    await waitFor(() => expect(regenerated).toBe(true))
  })
})

import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { NotificationBell } from './NotificationBell'
import type { NotificationsResponse } from './types'

function response(overrides: Partial<NotificationsResponse['meta']> = {}): NotificationsResponse {
  return {
    data: [
      {
        id: 'n1',
        data: {
          type: 'invoice.issued',
          message: 'Nouvelle facture EW-2026-00001.',
          url: '/factures',
        },
        read_at: null,
        is_read: false,
        created_at: '2026-06-07T08:00:00+00:00',
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, unread_count: 1, ...overrides },
  }
}

describe('NotificationBell', () => {
  it('affiche le badge de non-lues et la liste au clic', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/notifications', () => HttpResponse.json(response())))

    renderWithProviders(<NotificationBell />)

    const bell = await screen.findByRole('button', { name: /1 non lue/i })
    await user.click(bell)

    expect(await screen.findByText('Nouvelle facture EW-2026-00001.')).toBeInTheDocument()
  })

  it('marque tout comme lu', async () => {
    const user = userEvent.setup()
    let readAllCalled = false
    server.use(
      http.get('/api/notifications', () => HttpResponse.json(response())),
      http.post('/api/notifications/read-all', () => {
        readAllCalled = true
        return HttpResponse.json({ message: 'ok' })
      }),
    )

    renderWithProviders(<NotificationBell />)

    await user.click(await screen.findByRole('button', { name: /non lue/i }))
    await user.click(await screen.findByRole('button', { name: /tout marquer comme lu/i }))

    await waitFor(() => expect(readAllCalled).toBe(true))
  })

  it('n’affiche pas de badge sans notification non lue', async () => {
    server.use(
      http.get('/api/notifications', () =>
        HttpResponse.json({
          ...response(),
          data: [],
          meta: { ...response().meta, unread_count: 0 },
        }),
      ),
    )

    renderWithProviders(<NotificationBell />)

    expect(await screen.findByRole('button', { name: 'Notifications' })).toBeInTheDocument()
  })
})

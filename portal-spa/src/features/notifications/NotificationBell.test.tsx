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

  it('ouvre un panneau simple (pas de pattern menu) avec gestion du focus', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/notifications', () => HttpResponse.json(response())))

    renderWithProviders(<NotificationBell />)

    const bell = await screen.findByRole('button', { name: /1 non lue/i })
    await user.click(bell)

    const panel = await screen.findByRole('region', { name: 'Notifications' })
    expect(panel).toHaveFocus()
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()
    expect(screen.queryByRole('menuitem')).not.toBeInTheDocument()

    await user.keyboard('{Escape}')
    expect(screen.queryByRole('region', { name: 'Notifications' })).not.toBeInTheDocument()
    expect(bell).toHaveFocus()
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

  it('marque une notification comme lue individuellement sans naviguer', async () => {
    const user = userEvent.setup()
    let readId: string | null = null
    server.use(
      http.get('/api/notifications', () => HttpResponse.json(response())),
      http.post('/api/notifications/:id/read', ({ params }) => {
        readId = params.id as string
        return HttpResponse.json({ message: 'ok' })
      }),
    )

    renderWithProviders(<NotificationBell />, { route: '/' })

    await user.click(await screen.findByRole('button', { name: /1 non lue/i }))
    await user.click(screen.getByRole('button', { name: 'Marquer comme lu' }))

    await waitFor(() => expect(readId).toBe('n1'))
    // Le panneau reste ouvert (pas de navigation déclenchée par ce bouton).
    expect(screen.getByRole('region', { name: 'Notifications' })).toBeInTheDocument()
  })

  it('affiche une erreur de chargement avec un bouton Réessayer', async () => {
    const user = userEvent.setup()
    let calls = 0
    server.use(
      http.get('/api/notifications', () => {
        calls += 1
        return calls === 1
          ? HttpResponse.json({ message: 'Erreur serveur' }, { status: 500 })
          : HttpResponse.json(response())
      }),
    )

    renderWithProviders(<NotificationBell />)

    await user.click(await screen.findByRole('button', { name: 'Notifications' }))
    expect(await screen.findByText('Impossible de charger les notifications.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByText('Nouvelle facture EW-2026-00001.')).toBeInTheDocument()
  })

  it('charge la page suivante avec « Charger plus »', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/notifications', ({ request }) => {
        const page = new URL(request.url).searchParams.get('page') ?? '1'
        if (page === '2') {
          return HttpResponse.json({
            data: [
              {
                id: 'n2',
                data: { type: 'invoice.issued', message: 'Deuxième page.' },
                read_at: null,
                is_read: true,
                created_at: '2026-06-06T08:00:00+00:00',
              },
            ],
            meta: { current_page: 2, last_page: 2, per_page: 1, total: 2, unread_count: 1 },
          })
        }
        return HttpResponse.json(response({ current_page: 1, last_page: 2, per_page: 1, total: 2 }))
      }),
    )

    renderWithProviders(<NotificationBell />)

    await user.click(await screen.findByRole('button', { name: /1 non lue/i }))
    await user.click(await screen.findByRole('button', { name: 'Charger plus' }))

    expect(await screen.findByText('Deuxième page.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Charger plus' })).not.toBeInTheDocument()
  })
})

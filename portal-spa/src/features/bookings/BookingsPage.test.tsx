import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { BookingsPage } from './BookingsPage'

const ROOM = {
  id: 7,
  type: 'meeting_room',
  name: 'Salle Rhône',
  description: 'Salle de réunion 6 places',
  capacity: 6,
  features: ['écran'],
  floor: 1,
  svg_desk_id: null,
  external_half_day_price_ht: null,
}

function mockUser(permissions: string[]): AuthUser {
  return {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    two_factor_enabled: false,
    roles: [],
    permissions,
  }
}

function bookingsPage(data: unknown[]) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
  })
}

/**
 * Le flag resident/external vient de useAuth → on alimente le cache TanStack Query
 * `['auth','me']` via le handler /api/user (chargé par AuthProvider).
 */
function withCurrentUser(permissions: string[]) {
  server.use(
    http.get('/api/user', () => HttpResponse.json(mockUser(permissions))),
    // La page monte la section d'abonnement iCal (C9.2) → handler par défaut.
    http.get('/api/calendar', () =>
      HttpResponse.json({
        enabled: true,
        urls: {
          mine: 'http://localhost/calendar/tok/mine.ics',
          entity: 'http://localhost/calendar/tok/entity.ics',
        },
      }),
    ),
  )
}

describe('BookingsPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(new Date('2026-06-10T09:00:00'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('liste mes réservations', async () => {
    withCurrentUser(['create-own-booking'])
    server.use(
      http.get('/api/rooms', () => HttpResponse.json({ data: [ROOM] })),
      http.get('/api/bookings', () =>
        bookingsPage([
          {
            id: 100,
            resource_id: 7,
            resource_name: 'Salle Rhône',
            title: 'Réunion équipe',
            starts_at: '2026-06-12T10:00:00.000Z',
            ends_at: '2026-06-12T11:00:00.000Z',
            status: 'confirmed',
            is_paid: false,
            cancellable: true,
          },
        ]),
      ),
    )

    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByText('Réunion équipe')).toBeInTheDocument()
    expect(screen.getByText('Confirmée')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /annuler/i })).toBeInTheDocument()
  })

  it('permet à un résident de réserver un créneau horaire', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    withCurrentUser(['create-own-booking'])
    const createSpy = vi.fn()
    server.use(
      http.get('/api/rooms', () => HttpResponse.json({ data: [ROOM] })),
      http.get('/api/bookings', () => bookingsPage([])),
      http.get('/api/rooms/:id/availability', () =>
        HttpResponse.json({ date: '2026-06-12', busy: [], external_slots: [], is_external: false }),
      ),
      http.post('/api/bookings', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json({ data: { id: 1 } }, { status: 201 })
      }),
    )

    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.selectOptions(await screen.findByLabelText('Salle'), '7')
    const bookButtons = await screen.findAllByRole('button', { name: /réserver le créneau/i })
    await user.click(bookButtons[0] as HTMLElement)

    await waitFor(() => expect(screen.getByText('Réservation confirmée.')).toBeInTheDocument())
    expect(createSpy).toHaveBeenCalledTimes(1)
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({ resource_id: 7 })
  })

  it('affiche le message d’erreur sur conflit de créneau (409)', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    withCurrentUser(['create-own-booking'])
    server.use(
      http.get('/api/rooms', () => HttpResponse.json({ data: [ROOM] })),
      http.get('/api/bookings', () => bookingsPage([])),
      http.get('/api/rooms/:id/availability', () =>
        HttpResponse.json({ date: '2026-06-12', busy: [], external_slots: [], is_external: false }),
      ),
      http.post('/api/bookings', () =>
        HttpResponse.json({ message: 'Ce créneau est déjà réservé.' }, { status: 409 }),
      ),
    )

    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.selectOptions(await screen.findByLabelText('Salle'), '7')
    const bookButtons = await screen.findAllByRole('button', { name: /réserver le créneau/i })
    await user.click(bookButtons[0] as HTMLElement)

    expect(await screen.findByText('Ce créneau est déjà réservé.')).toBeInTheDocument()
  })

  it('permet à un external de réserver une demi-journée payante', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    withCurrentUser(['create-paid-booking'])
    const createSpy = vi.fn()
    server.use(
      http.get('/api/rooms', () => HttpResponse.json({ data: [ROOM] })),
      http.get('/api/bookings', () => bookingsPage([])),
      http.get('/api/rooms/:id/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          busy: [],
          external_slots: [
            {
              period: 'morning',
              starts_at: '2026-06-12T07:00:00.000Z',
              ends_at: '2026-06-12T11:00:00.000Z',
            },
          ],
          is_external: true,
        }),
      ),
      http.post('/api/bookings', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json({ data: { id: 2 } }, { status: 201 })
      }),
    )

    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.selectOptions(await screen.findByLabelText('Salle'), '7')
    const slotList = await screen.findByRole('heading', { name: /demi-journées disponibles/i })
    const list = slotList.parentElement as HTMLElement
    await user.click(within(list).getByRole('button', { name: /réserver/i }))

    await waitFor(() => expect(screen.getByText('Réservation confirmée.')).toBeInTheDocument())
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({ resource_id: 7, period: 'morning' })
  })
})

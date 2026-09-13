import { screen, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DashboardUpcomingBookings } from './DashboardUpcomingBookings'

describe('DashboardUpcomingBookings (R-05/R-07, PRD §3.3.2)', () => {
  it('demande les prochaines résas (upcoming, 3 max) et affiche libellé, salle, créneau', async () => {
    const seen: URLSearchParams[] = []
    server.use(
      http.get('/api/bookings', ({ request }) => {
        seen.push(new URL(request.url).searchParams)
        return HttpResponse.json({
          data: [
            {
              id: 1,
              resource_id: 2,
              resource_name: 'Salle de réunion 2',
              title: 'Point client',
              starts_at: '2026-09-15T10:00:00+02:00',
              ends_at: '2026-09-15T12:00:00+02:00',
              status: 'confirmed',
              is_paid: false,
              cancellable: true,
            },
            {
              id: 2,
              resource_id: 1,
              resource_name: 'Salle de réunion 1',
              title: null,
              starts_at: '2026-09-17T14:00:00+02:00',
              ends_at: '2026-09-17T16:00:00+02:00',
              status: 'confirmed',
              is_paid: false,
              cancellable: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 3, total: 2 },
        })
      }),
    )

    renderWithProviders(<DashboardUpcomingBookings />)

    expect(await screen.findByText('Point client — Salle de réunion 2')).toBeInTheDocument()
    expect(screen.getByText('Salle de réunion 1')).toBeInTheDocument()
    expect(screen.getByText(/15 septembre 2026/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Module réservations →' })).toHaveAttribute(
      'href',
      '/bookings',
    )
    expect(seen[0]?.get('upcoming')).toBe('1')
    expect(seen[0]?.get('per_page')).toBe('3')
  })

  it('affiche « Aucune réservation à venir » quand la liste est vide', async () => {
    server.use(
      http.get('/api/bookings', () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 3, total: 0 },
        }),
      ),
    )

    renderWithProviders(<DashboardUpcomingBookings />)

    expect(await screen.findByText('Aucune réservation à venir.')).toBeInTheDocument()
  })

  it('affiche le nom de la ressource à droite du libellé, sur chaque ligne (R-07)', async () => {
    server.use(
      http.get('/api/bookings', () =>
        HttpResponse.json({
          data: [
            {
              id: 1,
              resource_id: 2,
              resource_name: 'Salle de réunion 2',
              title: 'Point client',
              starts_at: '2026-09-15T10:00:00+02:00',
              ends_at: '2026-09-15T12:00:00+02:00',
              status: 'confirmed',
              is_paid: false,
              cancellable: true,
            },
            {
              id: 2,
              resource_id: 1,
              resource_name: 'Salle de réunion 1',
              // Sans libellé : seul le nom de la ressource doit rester affiché.
              title: null,
              starts_at: '2026-09-17T14:00:00+02:00',
              ends_at: '2026-09-17T16:00:00+02:00',
              status: 'confirmed',
              is_paid: false,
              cancellable: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 3, total: 2 },
        }),
      ),
    )

    renderWithProviders(<DashboardUpcomingBookings />)

    const rows = await screen.findAllByRole('listitem')
    expect(rows).toHaveLength(2)
    expect(
      within(rows[0] as HTMLElement).getByText('Point client — Salle de réunion 2'),
    ).toBeInTheDocument()
    expect(within(rows[1] as HTMLElement).getByText('Salle de réunion 1')).toBeInTheDocument()
  })
})

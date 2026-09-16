import { screen, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DashboardUpcomingBookings } from './DashboardUpcomingBookings'

describe('DashboardUpcomingBookings (R-05/R-07, PRD §3.3.2, maquette C14)', () => {
  it('demande les prochaines résas (upcoming, 3 max) et affiche libellé, horaire et salle', async () => {
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

    const rows = await screen.findAllByRole('listitem')
    expect(rows).toHaveLength(2)

    // Libellé : le titre du user quand présent, sinon la ressource (R-07) —
    // qui apparaît alors aussi dans le badge (redondant mais sans ambiguïté
    // de sens ; `selector` cible le badge pour lever l'ambiguïté de requête).
    expect(within(rows[0] as HTMLElement).getByText('Point client')).toBeInTheDocument()
    expect(within(rows[0] as HTMLElement).getByText('10:00 – 12:00')).toBeInTheDocument()
    expect(
      within(rows[1] as HTMLElement).getByText('Salle de réunion 1', {
        selector: '[data-slot="badge"]',
      }),
    ).toBeInTheDocument()

    // Badge salle : présent sur chaque ligne.
    expect(
      within(rows[0] as HTMLElement).getByText('Salle de réunion 2', {
        selector: '[data-slot="badge"]',
      }),
    ).toBeInTheDocument()

    expect(screen.getByRole('link', { name: 'Tout voir' })).toHaveAttribute('href', '/bookings')
    expect(screen.getByRole('link', { name: 'Nouvelle réservation' })).toHaveAttribute(
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
    // Le bouton reste proposé même sans résa à venir.
    expect(screen.getByRole('link', { name: 'Nouvelle réservation' })).toBeInTheDocument()
  })
})

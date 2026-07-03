import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DashboardAnnouncements } from './DashboardAnnouncements'
import type { Announcement } from './types'

function announcement(id: number, title: string): Announcement {
  return {
    id,
    type: 'info',
    title,
    body: `Contenu de ${title}.`,
    published_at: '2026-07-01T10:00:00+02:00',
    event_starts_at: null,
    event_ends_at: null,
    location: null,
    requires_registration: false,
    max_participants: null,
    registered_count: null,
    spots_left: null,
    my_registration_status: null,
    is_registered: false,
    is_registrable: false,
  }
}

function page(data: Announcement[]) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: 1, per_page: 10, total: data.length },
  })
}

describe('DashboardAnnouncements', () => {
  it('affiche au plus 3 actualités et le lien vers la liste complète', async () => {
    server.use(
      http.get('/api/announcements', () =>
        page([1, 2, 3, 4].map((id) => announcement(id, `Actualité ${id}`))),
      ),
    )

    renderWithProviders(<DashboardAnnouncements />)

    expect(await screen.findByText('Actualité 1')).toBeInTheDocument()
    expect(screen.getByText('Actualité 3')).toBeInTheDocument()
    expect(screen.queryByText('Actualité 4')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir toutes les actualités →' })).toHaveAttribute(
      'href',
      '/announcements',
    )
  })

  it('affiche l’état vide', async () => {
    server.use(http.get('/api/announcements', () => page([])))

    renderWithProviders(<DashboardAnnouncements />)

    expect(await screen.findByText('Aucune actualité pour le moment.')).toBeInTheDocument()
  })
})

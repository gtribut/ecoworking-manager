import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { AnnouncementsPage } from './AnnouncementsPage'
import type { Announcement } from './types'

function makeAnnouncement(overrides: Partial<Announcement> = {}): Announcement {
  return {
    id: 1,
    type: 'info',
    title: 'Fermeture exceptionnelle',
    body: 'Le coworking sera fermé le 14 juillet.',
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
    ...overrides,
  }
}

function page(data: Announcement[], lastPage = 1) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: lastPage, per_page: 10, total: data.length },
  })
}

describe('AnnouncementsPage', () => {
  it('affiche les annonces avec badge de type et lien vers le détail', async () => {
    server.use(
      http.get('/api/announcements', () =>
        page([
          makeAnnouncement(),
          makeAnnouncement({
            id: 2,
            type: 'event',
            title: 'Apéro d’été',
            body: 'Rendez-vous sur la terrasse pour fêter l’été ensemble.',
            event_starts_at: '2026-07-10T18:30:00+02:00',
            location: 'Terrasse',
            requires_registration: true,
            is_registrable: true,
          }),
        ]),
      ),
    )

    renderWithProviders(<AnnouncementsPage />)

    expect(await screen.findByText('Fermeture exceptionnelle')).toBeInTheDocument()
    expect(screen.getByText('Info')).toBeInTheDocument()
    expect(screen.getByText('Événement')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /l’actualité Apéro d’été/i })).toHaveAttribute(
      'href',
      '/announcements/2',
    )
  })

  it('affiche un message quand aucune actualité', async () => {
    server.use(http.get('/api/announcements', () => page([])))

    renderWithProviders(<AnnouncementsPage />)

    expect(await screen.findByText('Aucune actualité pour le moment.')).toBeInTheDocument()
  })

  it('tronque le corps en extrait court sur les cards', async () => {
    const longBody = 'a'.repeat(150)
    server.use(http.get('/api/announcements', () => page([makeAnnouncement({ body: longBody })])))

    renderWithProviders(<AnnouncementsPage />)

    expect(await screen.findByText(`${'a'.repeat(100)}…`)).toBeInTheDocument()
    expect(screen.queryByText(longBody)).not.toBeInTheDocument()
  })
})

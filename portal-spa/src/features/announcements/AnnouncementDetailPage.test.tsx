import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { AnnouncementDetailPage } from './AnnouncementDetailPage'
import type { Announcement } from './types'

function member(permissions: string[] = ['register-event']): AuthUser {
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

function event(overrides: Partial<Announcement> = {}): Announcement {
  return {
    id: 7,
    type: 'event',
    title: 'Atelier compost',
    body: 'Venez apprendre à composter.',
    published_at: '2026-07-01T10:00:00+02:00',
    event_starts_at: '2026-07-20T12:00:00+02:00',
    event_ends_at: null,
    location: 'Cour intérieure',
    requires_registration: true,
    max_participants: 10,
    registered_count: 3,
    spots_left: 7,
    my_registration_status: null,
    is_registered: false,
    is_registrable: true,
    ...overrides,
  }
}

function renderDetail() {
  return renderWithProviders(
    <Routes>
      <Route path="/announcements/:id" element={<AnnouncementDetailPage />} />
    </Routes>,
    { route: '/announcements/7', withAuth: true },
  )
}

describe('AnnouncementDetailPage', () => {
  it('affiche le détail d’un événement (lieu, jauge, corps)', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/announcements/7', () => HttpResponse.json({ data: event() })),
    )

    renderDetail()

    expect(await screen.findByRole('heading', { name: 'Atelier compost' })).toBeInTheDocument()
    expect(screen.getByText('Cour intérieure')).toBeInTheDocument()
    expect(screen.getByText('3 / 10')).toBeInTheDocument()
    expect(screen.getByText('Venez apprendre à composter.')).toBeInTheDocument()
  })

  it('permet de s’inscrire puis reflète l’inscription', async () => {
    let registered = false
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/announcements/7', () =>
        HttpResponse.json({
          data: event({ is_registered: registered, registered_count: registered ? 4 : 3 }),
        }),
      ),
      http.post('/api/announcements/7/registration', () => {
        registered = true
        return HttpResponse.json({ message: 'Inscription confirmée.' }, { status: 201 })
      }),
    )

    renderDetail()

    await userEvent.click(await screen.findByRole('button', { name: 'M’inscrire à l’événement' }))

    expect(await screen.findByText('Vous êtes inscrit(e) à cet événement.')).toBeInTheDocument()
    expect(screen.getByText('4 / 10')).toBeInTheDocument()
  })

  it('permet de se désinscrire après confirmation', async () => {
    let registered = true
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/announcements/7', () =>
        HttpResponse.json({ data: event({ is_registered: registered }) }),
      ),
      http.delete('/api/announcements/7/registration', () => {
        registered = false
        return HttpResponse.json({ message: 'Inscription annulée.' })
      }),
    )

    renderDetail()

    await userEvent.click(await screen.findByRole('button', { name: 'Me désinscrire' }))
    // Confirmation accessible en deux temps (ConfirmButton).
    await userEvent.click(await screen.findByRole('button', { name: 'Confirmer' }))

    expect(
      await screen.findByRole('button', { name: 'M’inscrire à l’événement' }),
    ).toBeInTheDocument()
  })

  it('affiche l’erreur métier du serveur si l’inscription échoue (422)', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/announcements/7', () => HttpResponse.json({ data: event() })),
      http.post('/api/announcements/7/registration', () =>
        HttpResponse.json({ message: 'Cet événement est complet.' }, { status: 422 }),
      ),
    )

    renderDetail()

    await userEvent.click(await screen.findByRole('button', { name: 'M’inscrire à l’événement' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Cet événement est complet.')
  })

  it('signale un événement complet sans bouton d’inscription', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/announcements/7', () =>
        HttpResponse.json({
          data: event({ is_registrable: false, registered_count: 10, spots_left: 0 }),
        }),
      ),
    )

    renderDetail()

    expect(await screen.findByText('Événement complet.')).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'M’inscrire à l’événement' }),
    ).not.toBeInTheDocument()
  })

  it('masque le RSVP pour un membre sans la permission register-event', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member([]))),
      http.get('/api/announcements/7', () => HttpResponse.json({ data: event() })),
    )

    renderDetail()

    expect(await screen.findByRole('heading', { name: 'Atelier compost' })).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'M’inscrire à l’événement' }),
    ).not.toBeInTheDocument()
  })
})

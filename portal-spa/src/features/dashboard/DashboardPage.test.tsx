import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import {
  BILLING_PERMISSIONS,
  MEMBER_PERMISSIONS,
  makeAuthUser,
  renderWithProviders,
} from '@/test/utils'
import { DashboardPage } from './DashboardPage'

const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}

/** Profil minimal, suffisant pour la tuile KPI « Mon bureau » (résident). */
const profileWithDesk = {
  user: {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    notify_email: true,
    notify_in_app: true,
  },
  profile: {
    id: 10,
    status: 'active',
    job_title: null,
    bio: null,
    interests: null,
    linkedin_url: null,
    website_url: null,
    birth_date: null,
    photo: null,
    show_in_directory: false,
    newsletter_opt_in: false,
    arrival_date: null,
    desk: { id: 4, name: '12', floor: 1 },
  },
  company: null,
}

function mockDashboard(authUser: AuthUser): void {
  server.use(
    http.get('/api/user', () => HttpResponse.json(authUser)),
    http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
    http.get('/api/invoices', () => HttpResponse.json(emptyPage)),
    http.get('/api/bookings', () => HttpResponse.json(emptyPage)),
    http.get('/api/announcements', () => HttpResponse.json(emptyPage)),
    // KPI « Mon bureau » (résident) / « Tickets restants » (external) / bandeau
    // factures (bloc entité) : requêtes déclenchées selon le rôle testé, mockées
    // ici pour tous les scénarios (`onUnhandledRequest: 'error'`, cf. setup.ts).
    http.get('/api/profile', () => HttpResponse.json(profileWithDesk)),
    http.get('/api/tickets', () =>
      HttpResponse.json({ balances: { desk_half_day: 0, meeting_room_half_day: 0 }, tickets: [] }),
    ),
    http.get('/api/billing/entity', () => HttpResponse.json({ data: [] })),
  )
}

describe('DashboardPage — blocs et tuiles par rôle (PRD §2.5, §3.3)', () => {
  it('résident avec bureau : résas, actualités et tuile « Ma présence », sans factures', async () => {
    mockDashboard(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<DashboardPage />, { withAuth: true })

    expect(
      await screen.findByRole('heading', { name: 'Mes prochaines réservations' }),
    ).toBeVisible()
    expect(screen.getByRole('heading', { name: 'Actualités Ecoworking' })).toBeVisible()
    // Le lien « Ma présence » vient désormais du KPI « Mon bureau »
    // (`useProfile()`, asynchrone) : attendre son chargement.
    expect(await screen.findByRole('link', { name: /Ma présence/ })).toBeVisible()
    expect(screen.queryByRole('heading', { name: 'Mes dernières factures' })).toBeNull()
  })

  it('membre additionnel : pas de tuile « Ma présence »', async () => {
    mockDashboard(makeAuthUser({ has_desk: false, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<DashboardPage />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Actualités Ecoworking' })).toBeVisible()
    expect(screen.queryByRole('link', { name: /Ma présence/ })).toBeNull()
  })

  it('contact facturation pur : factures et actualités, ni résas ni présence', async () => {
    mockDashboard(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<DashboardPage />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Mes dernières factures' })).toBeVisible()
    expect(screen.getByRole('heading', { name: 'Actualités Ecoworking' })).toBeVisible()
    expect(screen.queryByRole('heading', { name: 'Mes prochaines réservations' })).toBeNull()
    expect(screen.queryByRole('link', { name: /Ma présence/ })).toBeNull()
  })

  it('passe la grille en une colonne quand la première n’a aucun bloc', async () => {
    mockDashboard(makeAuthUser({ permissions: [] }))

    const { container } = renderWithProviders(<DashboardPage />, { withAuth: true })

    await screen.findByRole('heading', { name: 'Actualités Ecoworking' })
    expect(container.querySelector('.lg\\:grid-cols-2')).toBeNull()
  })
})

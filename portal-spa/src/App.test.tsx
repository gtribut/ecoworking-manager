import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { App } from './App'

const MEMBER_PERMISSIONS = [
  'view-own-bookings',
  'view-bookings-calendar',
  'create-own-booking',
  'manage-own-booking',
  'register-event',
  'validate-internal-document',
  'view-annuaire',
]

const BILLING_PERMISSIONS = [
  'view-billing-section',
  'view-entity-invoices',
  'view-entity-admin-documents',
]

function user(overrides: Partial<AuthUser> = {}): AuthUser {
  return {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    has_desk: false,
    two_factor_enabled: false,
    roles: [],
    permissions: [],
    ...overrides,
  }
}

const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}

/** Toutes les listes du portail répondent « vide » : seul le routage est testé. */
function mockPortal(authUser: AuthUser): void {
  server.use(
    http.get('/api/user', () => HttpResponse.json(authUser)),
    http.get('/api/notifications', () =>
      HttpResponse.json({ ...emptyPage, meta: { ...emptyPage.meta, unread_count: 0 } }),
    ),
    http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
    http.get('/api/invoices', () => HttpResponse.json(emptyPage)),
    http.get('/api/bookings', () => HttpResponse.json(emptyPage)),
    http.get('/api/announcements', () => HttpResponse.json(emptyPage)),
    http.get('/api/rooms', () => HttpResponse.json({ data: [] })),
    http.get('/api/presence', () => HttpResponse.json({ present_days: [], absences: [] })),
  )
}

describe('App — gardes de route par rôle (PRD §2.5)', () => {
  it('renvoie un contact facturation pur à l’accueil depuis /bookings et /announcements', async () => {
    mockPortal(user({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/bookings', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: /Bonjour Alex/ })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Réservations' })).toBeNull()
  })

  it('laisse un contact facturation accéder à ses factures', async () => {
    mockPortal(user({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/invoices', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Mes factures' })).toBeVisible()
  })

  it('renvoie un membre sans rôle billing à l’accueil depuis /invoices', async () => {
    mockPortal(user({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/invoices', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: /Bonjour Alex/ })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Mes factures' })).toBeNull()
  })

  it('renvoie un membre sans bureau attitré à l’accueil depuis /presence', async () => {
    mockPortal(user({ has_desk: false, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/presence', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: /Bonjour Alex/ })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Ma présence' })).toBeNull()
  })

  it('laisse un résident avec bureau attitré accéder à sa présence', async () => {
    mockPortal(user({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/presence', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Ma présence' })).toBeVisible()
  })

  it('renvoie un external à l’accueil depuis l’annuaire', async () => {
    mockPortal(
      user({
        permissions: ['view-own-bookings', 'view-bookings-calendar', 'create-paid-booking'],
      }),
    )

    renderWithProviders(<App />, { route: '/directory', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: /Bonjour Alex/ })).toBeVisible()
  })
})

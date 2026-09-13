import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import {
  BILLING_PERMISSIONS,
  EXTERNAL_PERMISSIONS,
  MEMBER_PERMISSIONS,
  makeAuthUser,
  renderWithProviders,
} from '@/test/utils'
import { App } from './App'

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
  it('refuse /bookings à un contact facturation pur, avec un écran « Accès refusé »', async () => {
    mockPortal(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/bookings', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Accès refusé' })).toBeVisible()
    expect(screen.getByRole('link', { name: 'Retour à l’accueil' })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Réservations' })).toBeNull()
  })

  it('laisse un contact facturation pur lire les actualités', async () => {
    mockPortal(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/announcements', withAuth: true })

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Actualités Ecoworking' }),
    ).toBeVisible()
  })

  it('laisse un contact facturation accéder à ses factures', async () => {
    mockPortal(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/invoices', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Mes factures' })).toBeVisible()
  })

  it('refuse /invoices à un membre sans rôle billing', async () => {
    mockPortal(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/invoices', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Accès refusé' })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Mes factures' })).toBeNull()
  })

  it('refuse /presence à un membre sans bureau attitré', async () => {
    mockPortal(makeAuthUser({ has_desk: false, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/presence', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Accès refusé' })).toBeVisible()
    expect(screen.queryByRole('heading', { level: 1, name: 'Ma présence' })).toBeNull()
  })

  it('laisse un résident avec bureau attitré accéder à sa présence', async () => {
    mockPortal(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/presence', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Ma présence' })).toBeVisible()
  })

  it('refuse l’annuaire à un external', async () => {
    mockPortal(makeAuthUser({ permissions: EXTERNAL_PERMISSIONS }))

    renderWithProviders(<App />, { route: '/directory', withAuth: true })

    expect(await screen.findByRole('heading', { level: 1, name: 'Accès refusé' })).toBeVisible()
  })
})

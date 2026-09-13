import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { Layout } from './Layout'

/** Permissions du tronc commun « rôle d'usage » (PermissionSeeder, PRD §2.5). */
const MEMBER_PERMISSIONS = [
  'view-own-bookings',
  'view-bookings-calendar',
  'create-own-booking',
  'manage-own-booking',
  'register-event',
  'validate-internal-document',
]

const BILLING_PERMISSIONS = [
  'view-billing-section',
  'view-entity-invoices',
  'view-entity-admin-documents',
  'request-entity-modification',
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

/** Rend le Layout connecté en tant que `authUser`, cloche de notifications muette. */
async function renderNav(authUser: AuthUser): Promise<void> {
  server.use(
    http.get('/api/user', () => HttpResponse.json(authUser)),
    http.get('/api/notifications', () =>
      HttpResponse.json({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, unread_count: 0 },
      }),
    ),
  )

  renderWithProviders(<Layout />, { withAuth: true })

  // Attend la résolution de /api/user : le nom affiché n'apparaît qu'ensuite,
  // donc la navigation rendue est bien celle du rôle testé.
  await screen.findAllByText(`${authUser.first_name} ${authUser.last_name}`)
}

/** Libellés de la navigation principale (desktop), dans l'ordre du DOM. */
function navLabels(): string[] {
  const navs = screen.getAllByRole('navigation', { name: 'Navigation principale' })
  const [desktopNav] = navs
  return Array.from(desktopNav?.querySelectorAll('a') ?? []).map(
    (link) => link.textContent?.trim() ?? '',
  )
}

describe('Layout — navigation filtrée par rôle (PRD §2.5)', () => {
  it('résident avec bureau attitré : tous les modules sauf factures et tickets', async () => {
    await renderNav(user({ has_desk: true, permissions: [...MEMBER_PERMISSIONS, 'view-annuaire'] }))

    expect(navLabels()).toEqual([
      'Accueil',
      'Réservations',
      'Actualités',
      'Présence',
      'Annuaire',
      'Documents',
      'Profil',
    ])
  })

  it('membre additionnel (sans bureau attitré) : pas de « Présence »', async () => {
    await renderNav(
      user({ has_desk: false, permissions: [...MEMBER_PERMISSIONS, 'view-annuaire'] }),
    )

    expect(navLabels()).not.toContain('Présence')
    expect(navLabels()).toContain('Réservations')
    expect(navLabels()).toContain('Annuaire')
  })

  it('external : ni « Présence » ni « Annuaire », mais « Tickets »', async () => {
    await renderNav(
      user({
        permissions: [
          'view-own-bookings',
          'view-bookings-calendar',
          'create-paid-booking',
          'manage-own-booking',
          'register-event',
          'validate-internal-document',
        ],
      }),
    )

    expect(navLabels()).toContain('Tickets')
    expect(navLabels()).not.toContain('Présence')
    expect(navLabels()).not.toContain('Annuaire')
  })

  it('contact facturation pur : accueil, profil, factures et documents seulement', async () => {
    await renderNav(user({ permissions: BILLING_PERMISSIONS }))

    expect(navLabels()).toEqual(['Accueil', 'Documents', 'Profil', 'Factures'])
  })

  it('résident contact facturation : les factures s’ajoutent à ses modules', async () => {
    await renderNav(
      user({
        has_desk: true,
        permissions: [...MEMBER_PERMISSIONS, 'view-annuaire', ...BILLING_PERMISSIONS],
      }),
    )

    expect(navLabels()).toContain('Factures')
    expect(navLabels()).toContain('Présence')
  })
})

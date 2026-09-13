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
import { Layout } from './Layout'

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
    await renderNav(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

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
    await renderNav(makeAuthUser({ has_desk: false, permissions: MEMBER_PERMISSIONS }))

    expect(navLabels()).not.toContain('Présence')
    expect(navLabels()).toContain('Réservations')
    expect(navLabels()).toContain('Annuaire')
  })

  it('external : ni « Présence » ni « Annuaire », mais « Tickets »', async () => {
    await renderNav(makeAuthUser({ permissions: EXTERNAL_PERMISSIONS }))

    expect(navLabels()).toContain('Tickets')
    expect(navLabels()).not.toContain('Présence')
    expect(navLabels()).not.toContain('Annuaire')
  })

  it('contact facturation pur : accueil, actualités, documents, profil et factures', async () => {
    await renderNav(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    expect(navLabels()).toEqual(['Accueil', 'Actualités', 'Documents', 'Profil', 'Factures'])
  })

  it('résident contact facturation : les factures s’ajoutent à ses modules', async () => {
    await renderNav(
      makeAuthUser({
        has_desk: true,
        permissions: [...MEMBER_PERMISSIONS, ...BILLING_PERMISSIONS],
      }),
    )

    expect(navLabels()).toContain('Factures')
    expect(navLabels()).toContain('Présence')
  })
})

import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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

/** Rend le shell connecté en tant que `authUser`, cloche de notifications muette. */
async function renderShell(authUser: AuthUser): Promise<void> {
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

  // Attend la résolution de /api/user : le bloc profil n'apparaît qu'ensuite,
  // donc la navigation rendue est bien celle du rôle testé.
  await screen.findAllByText(`${authUser.first_name} ${authUser.last_name}`)
}

/** Libellés de la sidebar (groupe principal + « Administratif »), dans l'ordre du DOM. */
function sidebarLabels(): string[] {
  const nav = screen.getByRole('navigation', { name: 'Navigation principale' })
  return Array.from(nav.querySelectorAll('a')).map((link) => link.textContent?.trim() ?? '')
}

/** Libellés des onglets de la bottom nav mobile, « Plus » compris. */
function bottomNavLabels(): string[] {
  const nav = screen.getByRole('navigation', { name: 'Navigation rapide' })
  return Array.from(nav.querySelectorAll('a, button')).map((item) => item.textContent?.trim() ?? '')
}

/*
 * Depuis C14 (U2), « Mon profil » n'est plus une entrée de navigation : il vit
 * dans le bloc profil du bas de sidebar (menu déroulant) et dans le Sheet
 * « Plus » en mobile. L'ordre suit les maquettes C14 ; le FILTRAGE par rôle est
 * inchangé (lot B, PRD §2.5) et reste ce que ces cas vérifient.
 */
describe('Layout — navigation filtrée par rôle (PRD §2.5)', () => {
  it('résident avec bureau attitré : tous les modules sauf factures et tickets', async () => {
    await renderShell(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    expect(sidebarLabels()).toEqual([
      'Accueil',
      'Réservations',
      'Présence',
      'Annuaire',
      'Actualités',
      'Documents',
    ])
  })

  it('membre additionnel (sans bureau attitré) : pas de « Présence »', async () => {
    await renderShell(makeAuthUser({ has_desk: false, permissions: MEMBER_PERMISSIONS }))

    expect(sidebarLabels()).not.toContain('Présence')
    expect(sidebarLabels()).toContain('Réservations')
    expect(sidebarLabels()).toContain('Annuaire')
  })

  it('external : ni « Présence » ni « Annuaire », mais « Tickets »', async () => {
    await renderShell(makeAuthUser({ permissions: EXTERNAL_PERMISSIONS }))

    expect(sidebarLabels()).toContain('Tickets')
    expect(sidebarLabels()).not.toContain('Présence')
    expect(sidebarLabels()).not.toContain('Annuaire')
  })

  it('contact facturation pur : accueil, actualités, documents et factures', async () => {
    await renderShell(makeAuthUser({ permissions: BILLING_PERMISSIONS }))

    expect(sidebarLabels()).toEqual(['Accueil', 'Actualités', 'Documents', 'Factures'])
  })

  it('résident contact facturation : les factures s’ajoutent à ses modules', async () => {
    await renderShell(
      makeAuthUser({
        has_desk: true,
        permissions: [...MEMBER_PERMISSIONS, ...BILLING_PERMISSIONS],
      }),
    )

    expect(sidebarLabels()).toContain('Factures')
    expect(sidebarLabels()).toContain('Présence')
  })

  it('« Mon profil » reste atteignable depuis le bloc profil de la sidebar', async () => {
    const user = userEvent.setup()
    await renderShell(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    expect(sidebarLabels()).not.toContain('Profil')

    // Deux déclencheurs coexistent dans le DOM : celui du bloc profil de la
    // sidebar (desktop, premier) et celui de la barre mobile — un seul est
    // visible à la fois, mais jsdom n'applique pas les media queries.
    const [sidebarTrigger] = screen.getAllByRole('button', { name: /Menu profil de/ })
    await user.click(sidebarTrigger as HTMLElement)

    expect(await screen.findByRole('menuitem', { name: /Mon profil/ })).toHaveAttribute(
      'href',
      '/profile',
    )
  })
})

describe('Layout — bottom nav mobile (PRD §3.9.2)', () => {
  it('résident : les quatre onglets attendus, puis « Plus »', async () => {
    await renderShell(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    expect(bottomNavLabels()).toEqual(['Accueil', 'Réservations', 'Présence', 'Actualités', 'Plus'])
  })

  it('external : « Tickets » prend la place de « Présence », absente pour ce rôle', async () => {
    await renderShell(makeAuthUser({ permissions: EXTERNAL_PERMISSIONS }))

    expect(bottomNavLabels()).toEqual(['Accueil', 'Réservations', 'Tickets', 'Actualités', 'Plus'])
  })

  it('« Plus » ouvre un Sheet avec les modules restants, le profil et la déconnexion', async () => {
    const user = userEvent.setup()
    await renderShell(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))

    await user.click(screen.getByRole('button', { name: 'Plus' }))

    const sheet = await screen.findByRole('dialog', { name: 'Plus' })
    const extra = within(sheet).getByRole('navigation', { name: 'Modules supplémentaires' })
    expect(Array.from(extra.querySelectorAll('a')).map((link) => link.textContent?.trim())).toEqual(
      ['Annuaire', 'Documents', 'Mon profil'],
    )
    expect(within(sheet).getByRole('button', { name: /Déconnexion/ })).toBeInTheDocument()
  })
})

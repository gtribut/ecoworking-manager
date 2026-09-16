import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { useAuth } from '@/features/auth/useAuth'
import { server } from '@/test/server'
import {
  BILLING_PERMISSIONS,
  EXTERNAL_PERMISSIONS,
  MEMBER_PERMISSIONS,
  makeAuthUser,
  renderWithProviders,
} from '@/test/utils'
import { bottomNavTabs } from './BottomNav'
import { useNavEntries } from './useNavEntries'

/** Applique la règle de remplissage aux modules réellement autorisés au rôle. */
function Probe() {
  const { user } = useAuth()
  const { all } = useNavEntries()
  // Rendu seulement une fois `/api/user` résolu : avant, aucune permission
  // n'est connue et les onglets seraient ceux d'un rôle vide.
  if (user === null) return null
  return (
    <output>
      {bottomNavTabs(all)
        .map((entry) => entry.label)
        .join(' · ')}
    </output>
  )
}

async function tabsFor(authUser: AuthUser): Promise<string[]> {
  server.use(http.get('/api/user', () => HttpResponse.json(authUser)))
  renderWithProviders(<Probe />, { withAuth: true })

  const output = await screen.findByRole('status')
  return (output.textContent ?? '').split(' · ').filter((label) => label !== '')
}

/*
 * Quatre onglets + « Plus » (ce dernier rendu par le composant, couvert par
 * `Layout.test.tsx`). Les emplacements attendus sont Accueil, Réservations,
 * Présence, Actualités ; un module non autorisé est remplacé **à sa place** par
 * le premier module autorisé hors de cette liste, dans l'ordre de la nav.
 */
describe('bottomNavTabs — remplissage par rôle (PRD §3.9.2)', () => {
  it('résident avec bureau attitré : les quatre emplacements nominaux', async () => {
    expect(
      await tabsFor(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS })),
    ).toEqual(['Accueil', 'Réservations', 'Présence', 'Actualités'])
  })

  it('membre additionnel : « Annuaire » remplace « Présence »', async () => {
    expect(
      await tabsFor(makeAuthUser({ has_desk: false, permissions: MEMBER_PERMISSIONS })),
    ).toEqual(['Accueil', 'Réservations', 'Annuaire', 'Actualités'])
  })

  it('external : « Tickets » remplace « Présence »', async () => {
    expect(await tabsFor(makeAuthUser({ permissions: EXTERNAL_PERMISSIONS }))).toEqual([
      'Accueil',
      'Réservations',
      'Tickets',
      'Actualités',
    ])
  })

  it('contact facturation pur : « Documents » puis « Factures » comblent les trous', async () => {
    expect(await tabsFor(makeAuthUser({ permissions: BILLING_PERMISSIONS }))).toEqual([
      'Accueil',
      'Documents',
      'Factures',
      'Actualités',
    ])
  })
})

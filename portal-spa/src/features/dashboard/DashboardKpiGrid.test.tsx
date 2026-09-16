import { screen, waitFor } from '@testing-library/react'
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
import { DashboardKpiGrid } from './DashboardKpiGrid'

const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
}

function mockCommon(authUser: AuthUser): void {
  server.use(
    http.get('/api/user', () => HttpResponse.json(authUser)),
    http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
  )
}

/**
 * Le sous-texte d'un KPI mélange souvent du texte brut et un lien/badge
 * imbriqué (ex. « Étage 2 · » + `<Link>Ma présence</Link>`) : le matcher par
 * défaut de `getByText` ne concatène que les nœuds texte **directs** d'un
 * élément (`getNodeText`), donc ignore le texte d'un enfant élément. On
 * compare ici le `textContent` complet (récursif) du candidat.
 */
function exactText(target: string) {
  return (_content: string, element: Element | null) => element?.textContent === target
}

describe('DashboardKpiGrid — mapping rôle → tuiles (PRD §3.3, maquette C14)', () => {
  it('résident : KPI résa + bureau, pas de tickets ni de facture', async () => {
    mockCommon(makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS }))
    server.use(
      http.get('/api/bookings', () =>
        HttpResponse.json({
          data: [
            {
              id: 1,
              resource_id: 2,
              resource_name: 'Salle de réunion 2',
              title: 'Point client',
              starts_at: '2026-09-17T10:00:00+02:00',
              ends_at: '2026-09-17T12:00:00+02:00',
              status: 'confirmed',
              is_paid: false,
              cancellable: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 1, total: 1 },
        }),
      ),
      http.get('/api/profile', () =>
        HttpResponse.json({
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
            desk: { id: 4, name: '12', floor: 2 },
          },
          company: null,
        }),
      ),
    )

    renderWithProviders(<DashboardKpiGrid />, { withAuth: true })

    // Attend une valeur (chargement asynchrone) avant de vérifier les
    // headings : ceux-ci sont rendus dès le premier tour (avant la donnée),
    // les checks synchrones ci-dessous doivent donc venir après.
    expect(await screen.findByText('Salle de réunion 2 · Point client')).toBeInTheDocument()
    expect(await screen.findByText('Bureau 12')).toBeInTheDocument()

    // Libellé de tuile = heading (RGAA 9.1, navigation par titres) : la
    // grille est déjà une <section> titrée par un h2 sr-only (dashboard-kpis-title).
    expect(screen.getByRole('heading', { name: 'Prochaine réservation' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Mon bureau' })).toBeInTheDocument()
    expect(screen.getByText(exactText('Étage 2 · Ma présence'))).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Tickets restants' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Dernière facture' })).not.toBeInTheDocument()
  })

  it('external : KPI tickets restants (soldes), pas de tuile bureau', async () => {
    mockCommon(makeAuthUser({ has_desk: false, permissions: EXTERNAL_PERMISSIONS }))
    server.use(
      http.get('/api/bookings', () => HttpResponse.json(emptyPage)),
      http.get('/api/tickets', () =>
        HttpResponse.json({
          balances: { desk_half_day: 3, meeting_room_half_day: 1 },
          tickets: [],
        }),
      ),
    )

    renderWithProviders(<DashboardKpiGrid />, { withAuth: true })

    expect(await screen.findByText('3 bureaux')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Tickets restants' })).toBeInTheDocument()
    expect(screen.getByText(exactText('1 salle · Bureaux nomades'))).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mon bureau' })).not.toBeInTheDocument()
  })

  it('contact facturation : KPI dernière facture, pas de résa ni de bureau', async () => {
    mockCommon(makeAuthUser({ permissions: BILLING_PERMISSIONS }))
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [
            {
              id: 1,
              number: 'EW-2026-00042',
              status: 'paid',
              is_credit_note: false,
              issued_at: '2026-09-01',
              due_at: '2026-09-15',
              total_ht: '100.00',
              total_vat: '20.00',
              total_ttc: '120.00',
              amount_paid: '120.00',
              pdf_available: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        }),
      ),
    )

    renderWithProviders(<DashboardKpiGrid />, { withAuth: true })

    expect(await screen.findByText('120,00 € TTC')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Dernière facture' })).toBeInTheDocument()
    expect(screen.getByText(exactText('EW-2026-00042 · Payée'))).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Prochaine réservation' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mon bureau' })).not.toBeInTheDocument()
  })

  it('aucune permission d’usage et aucun document en attente : grille de KPI vide (R-06)', async () => {
    mockCommon(makeAuthUser({ permissions: [] }))

    renderWithProviders(<DashboardKpiGrid />, { withAuth: true })

    // La tuile documents (`DashboardDocumentsToValidate`) se masque elle-même
    // une fois la liste résolue vide — aucune tuile ne reste alors.
    await waitFor(() =>
      expect(
        screen.queryByRole('heading', { name: 'Documents à valider' }),
      ).not.toBeInTheDocument(),
    )
    expect(screen.queryByRole('heading', { name: 'Prochaine réservation' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mon bureau' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Tickets restants' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Dernière facture' })).not.toBeInTheDocument()
    // Le h2 sr-only de la section reste présent même sans tuile.
    expect(screen.getByRole('heading', { name: 'Indicateurs clés' })).toBeInTheDocument()
  })

  it('affiche uniquement la tuile documents quand seul un document est en attente', async () => {
    mockCommon(makeAuthUser({ permissions: [] }))
    server.use(
      http.get('/api/documents/internal', () =>
        HttpResponse.json({
          data: [
            {
              id: 1,
              type: 'charter',
              title: 'Charte interne',
              version: '1.0',
              body: null,
              published_at: '2026-06-01T10:00:00+02:00',
              pdf_available: true,
              is_validated: false,
              validated_at: null,
            },
          ],
        }),
      ),
    )

    renderWithProviders(<DashboardKpiGrid />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Documents à valider' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Prochaine réservation' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mon bureau' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Tickets restants' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Dernière facture' })).not.toBeInTheDocument()
  })
})

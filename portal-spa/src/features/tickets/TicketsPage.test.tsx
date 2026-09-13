import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import {
  EXTERNAL_PERMISSIONS,
  MEMBER_PERMISSIONS,
  makeAuthUser,
  renderWithProviders,
} from '@/test/utils'
import { TicketsPage } from './TicketsPage'

function paginated(data: unknown[]) {
  return { data, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length } }
}

interface SetupOptions {
  permissions?: string[]
  balances?: { desk_half_day: number; meeting_room_half_day: number }
  tickets?: unknown[]
  occupations?: unknown[]
}

/** Câble `/api/user` (permissions), `/api/tickets` et `/api/desk-occupations`. */
function setup({
  permissions = EXTERNAL_PERMISSIONS,
  balances = { desk_half_day: 3, meeting_room_half_day: 2 },
  tickets = [],
  occupations = [],
}: SetupOptions = {}) {
  server.use(
    http.get('/api/user', () => HttpResponse.json(makeAuthUser({ permissions }))),
    http.get('/api/tickets', () => HttpResponse.json({ balances, tickets })),
    http.get('/api/desk-occupations', ({ request }) => {
      const past = new URL(request.url).searchParams.has('past')
      return HttpResponse.json(paginated(past ? [] : occupations))
    }),
  )
}

describe('TicketsPage', () => {
  it('affiche les soldes de tickets', async () => {
    setup({ balances: { desk_half_day: 4, meeting_room_half_day: 2 } })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    expect(await screen.findByText('4')).toBeInTheDocument()
    expect(screen.getByText('Demi-journées bureau nomade')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
  })

  it('affiche un seul message avec mailto par type de ticket épuisé (pas de doublon)', async () => {
    setup({ balances: { desk_half_day: 0, meeting_room_half_day: 0 } })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    // Salle : un seul encart, dans la section soldes (pas de flux de résa
    // dédié sur cette page).
    expect(await screen.findByText(/ticket salle de réunion : contactez/)).toBeInTheDocument()
    // Bureau : un seul encart, juste avant le formulaire de réservation — PAS
    // dans la section soldes en plus (review lot E pt.8).
    expect(screen.getByText(/Vous n’avez plus de ticket bureau nomade\./)).toBeInTheDocument()
    expect(screen.queryByText(/ticket bureau : contactez/)).not.toBeInTheDocument()

    const mailtoLinks = screen
      .getAllByRole('link', { name: 'Nous contacter' })
      .filter((link) => link.getAttribute('href')?.startsWith('mailto:'))
    expect(mailtoLinks).toHaveLength(2)
    for (const link of mailtoLinks) {
      expect(link).toHaveAttribute(
        'href',
        'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de tickets',
      )
    }
  })

  it('affiche le détail par ticket (statut, crédit, utilisation)', async () => {
    setup({
      tickets: [
        {
          id: 1,
          type: 'desk_half_day',
          status: 'used',
          credited_at: '2026-06-01T10:00:00+02:00',
          consumed_at: '2026-06-10T09:00:00+02:00',
          usage: { kind: 'desk_occupation', resource_name: 'Bureau Rhône', date: '2026-06-10' },
        },
        {
          id: 2,
          type: 'meeting_room_half_day',
          status: 'available',
          credited_at: '2026-06-02T10:00:00+02:00',
          consumed_at: null,
          usage: null,
        },
      ],
    })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await screen.findByText('Détail de mes tickets')
    expect(screen.getByText('Utilisé')).toBeInTheDocument()
    expect(screen.getByText('Disponible')).toBeInTheDocument()
    expect(screen.getByText(/Bureau Rhône/)).toBeInTheDocument()
  })

  it('cherche les bureaux disponibles puis réserve', async () => {
    const user = userEvent.setup()
    const createSpy = vi.fn()
    setup({ balances: { desk_half_day: 3, meeting_room_half_day: 0 } })
    server.use(
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          period: 'full_day',
          available: true,
          reason: null,
          count: 1,
          desks: [
            { id: 9, name: 'Bureau 12', floor: 2, svg_desk_id: null, features: [], capacity: 1 },
          ],
        }),
      ),
      http.post('/api/desk-occupations', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json(
          {
            data: {
              id: 1,
              desk_id: 9,
              desk_name: 'Bureau 12',
              desk_floor: 2,
              date: '2026-06-12',
              period: 'full_day',
              status: 'present',
              ticket: null,
              cancellable: true,
            },
          },
          { status: 201 },
        )
      }),
    )

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))
    const heading = await screen.findByRole('heading', { name: /bureau\(x\) disponible/i })
    const list = heading.parentElement as HTMLElement
    await user.click(within(list).getByRole('button', { name: /réserver le bureau/i }))

    await waitFor(() =>
      expect(screen.getByText('Bureau « Bureau 12 » réservé.')).toBeInTheDocument(),
    )
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({ desk_id: 9, period: 'full_day' })
  })

  it('refuse un week-end côté client (jours ouvrés uniquement) sans appeler l’API', async () => {
    const user = userEvent.setup()
    const availabilitySpy = vi.fn()
    setup({ balances: { desk_half_day: 3, meeting_room_half_day: 0 } })
    server.use(
      http.get('/api/desks/availability', () => {
        availabilitySpy()
        return HttpResponse.json({
          date: '',
          period: 'full_day',
          available: true,
          reason: null,
          count: 0,
          desks: [],
        })
      }),
    )

    renderWithProviders(<TicketsPage />, { withAuth: true })

    const dateInput = await screen.findByLabelText('Date')
    // Un samedi arbitraire dans le futur lointain (stable quel que soit le jour du run).
    await user.clear(dateInput)
    await user.type(dateInput, '2030-07-06')
    await user.click(screen.getByRole('button', { name: /voir les bureaux disponibles/i }))

    expect(
      await screen.findByText(
        'Les bureaux nomades ne sont réservables que les jours ouvrés (lundi à vendredi).',
      ),
    ).toBeInTheDocument()
    expect(availabilitySpy).not.toHaveBeenCalled()
  })

  it('affiche un message dédié pour un jour férié (non_working_day) renvoyé par le serveur', async () => {
    const user = userEvent.setup()
    setup({ balances: { desk_half_day: 3, meeting_room_half_day: 0 } })
    server.use(
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-07-14',
          period: 'full_day',
          available: false,
          reason: 'non_working_day',
          count: 0,
          desks: [],
        }),
      ),
    )

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))

    expect(
      await screen.findByText(
        'Ce jour n’est pas un jour ouvré (week-end ou jour férié) : aucun bureau nomade n’y est réservable.',
      ),
    ).toBeInTheDocument()
  })

  it('affiche « aucun bureau disponible » avec mailto quand le jour est ouvré mais complet', async () => {
    const user = userEvent.setup()
    setup({ balances: { desk_half_day: 3, meeting_room_half_day: 0 } })
    server.use(
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          period: 'full_day',
          available: true,
          reason: null,
          count: 0,
          desks: [],
        }),
      ),
    )

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))

    expect(await screen.findByText(/Aucun bureau disponible sur ce créneau/)).toBeInTheDocument()
    const link = screen.getByRole('link', { name: 'Nous contacter pour un bureau' })
    expect(link).toHaveAttribute(
      'href',
      'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de bureau',
    )
  })

  it('affiche le message d’erreur 422 quand plus de ticket', async () => {
    const user = userEvent.setup()
    setup({ balances: { desk_half_day: 1, meeting_room_half_day: 0 } })
    server.use(
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          period: 'full_day',
          available: true,
          reason: null,
          count: 1,
          desks: [
            { id: 9, name: 'Bureau 12', floor: 2, svg_desk_id: null, features: [], capacity: 1 },
          ],
        }),
      ),
      http.post('/api/desk-occupations', () =>
        HttpResponse.json({ message: 'Plus aucun ticket bureau disponible.' }, { status: 422 }),
      ),
    )

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))
    const heading = await screen.findByRole('heading', { name: /bureau\(x\) disponible/i })
    const list = heading.parentElement as HTMLElement
    await user.click(within(list).getByRole('button', { name: /réserver le bureau/i }))

    expect(await screen.findByText('Plus aucun ticket bureau disponible.')).toBeInTheDocument()
  })

  it("n'affiche pas le formulaire de réservation bureau à 0 ticket (message + mailto avant)", async () => {
    setup({ balances: { desk_half_day: 0, meeting_room_half_day: 2 } })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await screen.findByText('Réserver un bureau nomade')
    expect(
      screen.queryByRole('button', { name: /voir les bureaux disponibles/i }),
    ).not.toBeInTheDocument()
    expect(screen.getByText(/Vous n’avez plus de ticket bureau nomade/)).toBeInTheDocument()
  })

  // --- « Mes bureaux réservés » (lot E, PRD §3.5.9) -------------------------

  it('liste les bureaux réservés à venir et permet de les annuler', async () => {
    const user = userEvent.setup()
    setup({
      occupations: [
        {
          id: 5,
          desk_id: 9,
          desk_name: 'Bureau 12',
          desk_floor: 2,
          date: '2026-06-20',
          period: 'morning',
          status: 'present',
          ticket: { id: 42, type: 'desk_half_day' },
          cancellable: true,
        },
      ],
    })
    server.use(http.delete('/api/desk-occupations/5', () => HttpResponse.json({ message: 'ok' })))

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await screen.findByText('Mes bureaux réservés')
    await screen.findByText('Bureau 12')

    await user.click(screen.getByRole('button', { name: /^Annuler/ }))
    await user.click(await screen.findByRole('button', { name: 'Oui, annuler' }))

    const confirmation = await screen.findByText('Réservation du bureau « Bureau 12 » annulée.')
    expect(confirmation).toBeInTheDocument()
    // La ligne annulée est démontée (refetch « à venir ») : le focus ne doit
    // pas retomber sur <body>, il est reporté sur l'encart de confirmation
    // (review lot E pt.9).
    await waitFor(() => expect(confirmation.parentElement).toHaveFocus())
  })

  it('affiche un message explicatif au lieu du bouton Annuler quand le délai est dépassé', async () => {
    setup({
      occupations: [
        {
          id: 6,
          desk_id: 9,
          desk_name: 'Bureau 12',
          desk_floor: 2,
          date: '2026-06-20',
          period: 'morning',
          status: 'present',
          ticket: null,
          cancellable: false,
        },
      ],
    })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await screen.findByText('Bureau 12')
    expect(screen.getByText('Non annulable (délai dépassé)')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Annuler/ })).not.toBeInTheDocument()
  })

  it('ne propose pas « Mes bureaux réservés » à un membre non external', async () => {
    setup({ permissions: MEMBER_PERMISSIONS })

    renderWithProviders(<TicketsPage />, { withAuth: true })

    await screen.findByText('Mes soldes de tickets')
    expect(screen.queryByText('Mes bureaux réservés')).not.toBeInTheDocument()
  })
})

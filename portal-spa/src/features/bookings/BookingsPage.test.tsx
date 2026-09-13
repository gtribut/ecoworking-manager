import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import {
  EXTERNAL_PERMISSIONS,
  MEMBER_PERMISSIONS,
  makeAuthUser,
  renderWithProviders,
} from '@/test/utils'
import { BookingDialog } from './BookingDialog'
import { BookingsPage } from './BookingsPage'

const MEETING_ROOM = {
  id: 7,
  type: 'meeting_room',
  name: 'Salle Rhône',
  description: 'Salle de réunion 6 places',
  capacity: 6,
  features: ['écran'],
  floor: 1,
  external_half_day_price_ht: null,
  is_bookable: true,
}

const EVENT_ROOM = {
  id: 9,
  type: 'event_room',
  name: 'Salle événementielle',
  description: null,
  capacity: 60,
  features: [],
  floor: 0,
  external_half_day_price_ht: null,
  is_bookable: false,
}

/** Mercredi 2026-06-10 09:00 (heure locale) — jour de référence des tests. */
const TODAY = new Date('2026-06-10T09:00:00')

function bookingsPage(data: unknown[]) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
  })
}

function availability(slots: unknown[] = [], eventSlots: unknown[] = []) {
  return HttpResponse.json({
    from: '2026-06-08',
    to: '2026-06-14',
    rooms: [
      { ...MEETING_ROOM, slots },
      { ...EVENT_ROOM, slots: eventSlots },
    ],
  })
}

interface SetupOptions {
  permissions?: string[]
  slots?: unknown[]
  bookings?: unknown[]
  pastBookings?: unknown[]
  meetingTickets?: number
}

function setup({
  permissions = MEMBER_PERMISSIONS,
  slots = [],
  bookings = [],
  pastBookings = [],
  meetingTickets = 2,
}: SetupOptions = {}) {
  server.use(
    http.get('/api/user', () => HttpResponse.json(makeAuthUser({ permissions }))),
    http.get('/api/calendar', () =>
      HttpResponse.json({ enabled: true, urls: { mine: 'http://x/mine.ics', entity: null } }),
    ),
    http.get('/api/tickets', () =>
      HttpResponse.json({
        balances: { desk_half_day: 0, meeting_room_half_day: meetingTickets },
        tickets: [],
      }),
    ),
    http.get('/api/rooms', () => HttpResponse.json({ data: [MEETING_ROOM, EVENT_ROOM] })),
    http.get('/api/rooms/availability', () => availability(slots)),
    http.get('/api/bookings', ({ request }) =>
      bookingsPage(new URL(request.url).searchParams.has('past') ? pastBookings : bookings),
    ),
  )
}

describe('BookingsPage — calendrier des salles', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(TODAY)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('affiche la grille semaine avec toutes les salles et la navigation', async () => {
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Calendrier des salles' })).toBeVisible()
    // Semaine du lundi 8 juin 2026 (la date de référence est un mercredi).
    expect(screen.getByText(/Semaine du lundi 8 juin/, { selector: 'p' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Semaine précédente' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Semaine suivante' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aujourd’hui' })).toBeInTheDocument()
    expect(screen.getByLabelText('Aller à la semaine du')).toBeInTheDocument()
    // Filtre multi-salles : une case par salle, salle event incluse.
    expect(screen.getByRole('checkbox', { name: /Salle Rhône/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Salle événementielle/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Voir 24 h' })).not.toBeChecked()
  })

  it('navigue vers la semaine suivante et redemande la plage correspondante', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const ranges: string[] = []
    setup()
    server.use(
      http.get('/api/rooms/availability', ({ request }) => {
        const params = new URL(request.url).searchParams
        ranges.push(`${params.get('from')}→${params.get('to')}`)
        return availability()
      }),
    )

    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('heading', { name: 'Calendrier des salles' })
    await waitFor(() => expect(ranges).toContain('2026-06-08→2026-06-14'))

    await user.click(screen.getByRole('button', { name: 'Semaine suivante' }))

    await waitFor(() => expect(ranges).toContain('2026-06-15→2026-06-21'))
    expect(screen.getByText(/Semaine du lundi 15 juin/, { selector: 'p' })).toBeInTheDocument()
  })

  it('ouvre la vue jour par défaut sur mobile (matchMedia)', async () => {
    // Seule la requête de largeur répond « mobile » : le reste (thème) doit
    // continuer à passer par le stub jsdom, qui expose addEventListener.
    const realMatchMedia = window.matchMedia.bind(window)
    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) =>
      query.includes('max-width')
        ? ({ ...realMatchMedia(query), matches: true } as MediaQueryList)
        : realMatchMedia(query),
    )
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Calendrier des salles' })).toBeVisible()
    expect(screen.getByRole('button', { name: 'Jour précédent' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Salle Rhône' })).toBeInTheDocument()
  })

  it('étend la grille à 24 h via le toggle', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('heading', { name: 'Calendrier des salles' })

    // 8 h-20 h par défaut : ni 06:00 ni 22:00 dans les en-têtes de ligne.
    expect(screen.queryByRole('rowheader', { name: '06:00' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('checkbox', { name: 'Voir 24 h' }))

    expect(screen.getByRole('rowheader', { name: '00:00' })).toBeInTheDocument()
    expect(screen.getByRole('rowheader', { name: '23:00' })).toBeInTheDocument()
  })

  it('liste les 7 jours de la semaine dans l’alternative accessible', async () => {
    setup({
      slots: [
        {
          booking_id: null,
          is_mine: false,
          cancellable: false,
          starts_at: '2026-06-12T10:00:00+02:00',
          ends_at: '2026-06-12T11:00:00+02:00',
          label: 'Atelier',
          occupant: { kind: 'entity', first_name: null, last_name: null, company_name: 'Cabinet' },
        },
      ],
    })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByRole('heading', { name: 'Vue liste' })).toBeVisible()
    // Lundi 8 → dimanche 14 juin : un sous-titre par jour affiché.
    expect(screen.getByRole('heading', { name: 'lundi 8 juin' })).toBeVisible()
    expect(screen.getByRole('heading', { name: 'dimanche 14 juin' })).toBeVisible()
    // Et le créneau du vendredi 12 y figure en texte, avec son occupant.
    expect(screen.getByText(/Occupé par Cabinet — Atelier/)).toBeVisible()
  })

  it('n’offre aucune action sur une résa déjà commencée et ouvre la modale en lecture seule', async () => {
    setup({
      bookings: [
        {
          id: 77,
          resource_id: 7,
          resource_name: 'Salle Rhône',
          title: 'Déjà commencée',
          starts_at: '2026-06-10T08:00:00+02:00',
          ends_at: '2026-06-10T12:00:00+02:00',
          status: 'confirmed',
          is_paid: false,
          ticket: null,
          // Encore « à venir » (non terminée) mais le créneau a commencé.
          cancellable: false,
        },
      ],
    })
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByText('Déjà commencée')

    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
  })

  it('affiche la modale en lecture seule si l’état de la liste est périmé', () => {
    renderWithProviders(
      <BookingDialog
        target={{
          mode: 'edit',
          bookingId: 77,
          roomId: 7,
          roomName: 'Salle Rhône',
          startsAt: '2026-06-10T08:00:00+02:00',
          endsAt: '2026-06-10T12:00:00+02:00',
          title: 'Déjà commencée',
          cancellable: false,
        }}
        isExternal={false}
        onClose={() => undefined}
        onSuccess={() => undefined}
      />,
    )

    expect(screen.getByRole('dialog', { name: 'Ma réservation — Salle Rhône' })).toBeVisible()
    expect(screen.getByText(/n’est plus modifiable ni annulable depuis le portail/)).toBeVisible()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Enregistrer les modifications' }),
    ).not.toBeInTheDocument()
    expect(screen.getByText('Déjà commencée')).toBeVisible()
  })

  it('bascule en vue jour (une colonne par salle)', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('heading', { name: 'Calendrier des salles' })

    await user.click(screen.getByRole('button', { name: 'Jour' }))

    expect(screen.getByRole('button', { name: 'Jour précédent' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Salle Rhône' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Salle événementielle' })).toBeInTheDocument()
  })

  it('montre l’occupant, l’entité et le libellé d’une résa d’un autre membre, non modifiable', async () => {
    setup({
      slots: [
        {
          booking_id: null,
          is_mine: false,
          cancellable: false,
          starts_at: '2026-06-11T10:00:00+02:00',
          ends_at: '2026-06-11T11:00:00+02:00',
          label: 'Comité produit',
          occupant: {
            kind: 'member',
            first_name: 'Hugo',
            last_name: 'Discret',
            company_name: 'Atelier Numérique',
          },
        },
      ],
    })
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    const busy = await screen.findByRole('button', {
      name: /Occupé par Hugo Discret \(Atelier Numérique\) — Comité produit/,
    })
    // Consultable au clavier mais jamais modifiable (PRD §3.5.2).
    expect(busy).toHaveAttribute('aria-disabled', 'true')

    // Le panneau de détail s'alimente au focus clavier comme au survol.
    busy.focus()
    expect(
      await screen.findByText(/Occupé par Hugo Discret \(Atelier Numérique\) — Comité produit/, {
        selector: 'p',
      }),
    ).toBeVisible()

    await user.hover(busy)
    expect(
      screen.getByText(/Occupé par Hugo Discret \(Atelier Numérique\)/, { selector: 'p' }),
    ).toBeVisible()
  })

  it('propose de nous contacter au clic sur la salle événementielle', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    const cells = await screen.findAllByRole('button', {
      name: /Salle événementielle.*réservation sur demande/,
    })
    await user.click(cells[0] as HTMLElement)

    expect(await screen.findByText('Pour réserver cette salle, contactez-nous.')).toBeVisible()
    expect(screen.getByRole('link', { name: 'Nous contacter' })).toHaveAttribute(
      'href',
      'mailto:contact@ecoworking.fr?subject=[backend ecowo] Réservation salle événementielle',
    )
    // Aucune modale de réservation ne s'ouvre pour la salle event.
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('réserve un créneau libre depuis la modale (créneau pré-rempli)', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const created = vi.fn()
    setup()
    server.use(
      http.post('/api/bookings', async ({ request }) => {
        created(await request.json())
        return HttpResponse.json({ data: { id: 1 } }, { status: 201 })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    const free = await screen.findByRole('button', {
      name: /Réserver Salle Rhône, jeudi 11 juin 10:00/,
    })
    await user.click(free)

    const dialog = await screen.findByRole('dialog', { name: 'Réserver Salle Rhône' })
    expect(within(dialog).getByLabelText('Date')).toHaveValue('2026-06-11')
    expect(within(dialog).getByLabelText('Heure de début')).toHaveValue('10:00')
    expect(within(dialog).getByLabelText('Créneau personnalisé')).toBeChecked()

    await user.type(within(dialog).getByLabelText('Libellé (optionnel)'), 'Point équipe')
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    expect(created.mock.calls[0]?.[0]).toMatchObject({ resource_id: 7, title: 'Point équipe' })
    expect(await screen.findByText('Réservation confirmée.')).toBeVisible()
  })

  it('réserve une demi-journée via le toggle « Matin »', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const created = vi.fn()
    setup()
    server.use(
      http.post('/api/bookings', async ({ request }) => {
        created(await request.json())
        return HttpResponse.json({ data: { id: 1 } }, { status: 201 })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 10:00/ }),
    )
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByLabelText('Matin (9 h – 13 h)'))
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    const payload = created.mock.calls[0]?.[0] as { starts_at: string; ends_at: string }
    expect(new Date(payload.starts_at).getHours()).toBe(9)
    expect(new Date(payload.ends_at).getHours()).toBe(13)
  })

  it('affiche le conflit 409 et propose le créneau libre le plus proche', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({
      slots: [
        {
          booking_id: null,
          is_mine: false,
          cancellable: false,
          starts_at: '2026-06-11T10:00:00+02:00',
          ends_at: '2026-06-11T11:00:00+02:00',
          label: null,
          occupant: null,
        },
      ],
    })
    server.use(
      http.post('/api/bookings', () =>
        HttpResponse.json(
          { message: 'Ce créneau est déjà réservé pour cette salle.' },
          { status: 409 },
        ),
      ),
      // La suggestion se calcule sur une dispo FRAÎCHE : 09:00–10:00 vient
      // d'être pris par un tiers, 10:00–11:00 l'était déjà.
      http.get('/api/rooms/:id/availability', () =>
        HttpResponse.json({
          date: '2026-06-11',
          busy: [
            { starts_at: '2026-06-11T09:00:00+02:00', ends_at: '2026-06-11T10:00:00+02:00' },
            { starts_at: '2026-06-11T10:00:00+02:00', ends_at: '2026-06-11T11:00:00+02:00' },
          ],
          external_slots: [],
          is_external: false,
        }),
      ),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 09:00/ }),
    )
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    expect(
      await within(dialog).findByText('Ce créneau est déjà réservé pour cette salle.'),
    ).toBeVisible()
    expect(await within(dialog).findByText(/Créneau libre le plus proche/)).toBeVisible()
    // 08:00–09:00 : créneau libre le plus proche du 09:00 demandé, d'après la
    // dispo rafraîchie (09:00 et 10:00 sont pris).
    expect(within(dialog).getByRole('button', { name: '08:00 – 09:00' })).toBeVisible()
  })

  it('affiche les erreurs 422 sous les champs concernés', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    server.use(
      http.post('/api/bookings', () =>
        HttpResponse.json(
          {
            message: 'Les données sont invalides.',
            errors: { starts_at: ['Le créneau doit être dans le futur.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 10:00/ }),
    )
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    const field = await within(dialog).findByLabelText('Heure de début')
    await waitFor(() => expect(field).toHaveAttribute('aria-invalid', 'true'))
    expect(within(dialog).getByText('Le créneau doit être dans le futur.')).toBeVisible()
    expect(field).toHaveAttribute('aria-describedby', 'booking-start-error')
  })

  it('ferme la modale avec Échap', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 10:00/ }),
    )
    expect(await screen.findByRole('dialog')).toBeVisible()

    await user.keyboard('{Escape}')

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('ouvre « Modifier / Supprimer » sur sa propre réservation du calendrier', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const patched = vi.fn()
    setup({
      slots: [
        {
          booking_id: 42,
          is_mine: true,
          cancellable: true,
          starts_at: '2026-06-11T10:00:00+02:00',
          ends_at: '2026-06-11T11:00:00+02:00',
          label: 'Point équipe',
          occupant: {
            kind: 'member',
            first_name: 'Alex',
            last_name: 'Martin',
            company_name: 'Ecoworking',
          },
        },
      ],
    })
    server.use(
      http.patch('/api/bookings/42', async ({ request }) => {
        patched(await request.json())
        return HttpResponse.json({ data: { id: 42 } })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Ma réservation.*modifier ou supprimer/ }),
    )

    const dialog = await screen.findByRole('dialog', { name: 'Ma réservation — Salle Rhône' })
    expect(within(dialog).getByLabelText('Libellé (optionnel)')).toHaveValue('Point équipe')
    await user.clear(within(dialog).getByLabelText('Heure de fin'))
    await user.type(within(dialog).getByLabelText('Heure de fin'), '12:00')
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer les modifications' }))

    await waitFor(() => expect(patched).toHaveBeenCalledTimes(1))
    const payload = patched.mock.calls[0]?.[0] as { ends_at: string }
    expect(new Date(payload.ends_at).getHours()).toBe(12)
    expect(await screen.findByText('Réservation modifiée.')).toBeVisible()
  })

  it('supprime une réservation après confirmation', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const deleted = vi.fn()
    setup({
      slots: [
        {
          booking_id: 42,
          is_mine: true,
          cancellable: true,
          starts_at: '2026-06-11T10:00:00+02:00',
          ends_at: '2026-06-11T11:00:00+02:00',
          label: null,
          occupant: null,
        },
      ],
    })
    server.use(
      http.delete('/api/bookings/42', () => {
        deleted()
        return HttpResponse.json({ message: 'Réservation annulée.' })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Ma réservation.*modifier ou supprimer/ }),
    )
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Supprimer' }))
    await user.click(within(dialog).getByRole('button', { name: 'Oui, supprimer' }))

    await waitFor(() => expect(deleted).toHaveBeenCalledTimes(1))
    expect(await screen.findByText('Réservation annulée.')).toBeVisible()
  })
})

describe('BookingsPage — mes réservations', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(TODAY)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('liste les prochaines réservations par défaut et bascule sur l’historique', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({
      bookings: [
        {
          id: 100,
          resource_id: 7,
          resource_name: 'Salle Rhône',
          title: 'Réunion équipe',
          starts_at: '2026-06-12T10:00:00+02:00',
          ends_at: '2026-06-12T11:00:00+02:00',
          status: 'confirmed',
          is_paid: false,
          ticket: null,
          cancellable: true,
        },
      ],
      pastBookings: [
        {
          id: 90,
          resource_id: 7,
          resource_name: 'Salle Rhône',
          title: 'Rétro',
          starts_at: '2026-05-12T10:00:00+02:00',
          ends_at: '2026-05-12T11:00:00+02:00',
          status: 'confirmed',
          is_paid: false,
          ticket: null,
          cancellable: false,
        },
      ],
    })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(
      await screen.findByRole('heading', { name: 'Mes prochaines réservations' }),
    ).toBeVisible()
    expect(await screen.findByText('Réunion équipe')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Historique' }))

    expect(
      await screen.findByRole('heading', { name: 'Historique de mes réservations' }),
    ).toBeVisible()
    expect(await screen.findByText('Rétro')).toBeInTheDocument()
    // Une résa passée n'est plus modifiable.
    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
  })
})

describe('BookingsPage — external', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(TODAY)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('n’offre que les demi-journées et n’affiche pas le toggle 24 h', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const created = vi.fn()
    setup({ permissions: EXTERNAL_PERMISSIONS })
    server.use(
      http.post('/api/bookings', async ({ request }) => {
        created(await request.json())
        return HttpResponse.json({ data: { id: 3 } }, { status: 201 })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await screen.findByRole('heading', { name: 'Calendrier des salles' })
    expect(screen.queryByRole('checkbox', { name: 'Voir 24 h' })).not.toBeInTheDocument()
    expect(screen.getByText(/Tickets salle de réunion disponibles/)).toBeInTheDocument()

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 10:00/ }),
    )
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).queryByLabelText('Créneau personnalisé')).not.toBeInTheDocument()
    expect(within(dialog).queryByLabelText('Heure de début')).not.toBeInTheDocument()

    await user.click(within(dialog).getByLabelText('Après-midi (14 h – 18 h)'))
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    expect(created.mock.calls[0]?.[0]).toMatchObject({
      resource_id: 7,
      date: '2026-06-11',
      period: 'afternoon',
    })
  })

  it('invite à contacter Ecoworking quand le solde de tickets salle est nul', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({ permissions: EXTERNAL_PERMISSIONS, meetingTickets: 0 })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: /Réserver Salle Rhône, jeudi 11 juin 10:00/ }),
    )

    expect(
      await screen.findByText(
        'Vous n’avez plus de ticket salle de réunion. Contactez Ecoworking pour en obtenir.',
      ),
    ).toBeVisible()
    expect(screen.getByRole('link', { name: 'Nous contacter' })).toHaveAttribute(
      'href',
      'mailto:contact@ecoworking.fr?subject=[backend ecowo] Tickets salle de réunion',
    )
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('indique le ticket consommé par réservation', async () => {
    setup({
      permissions: EXTERNAL_PERMISSIONS,
      bookings: [
        {
          id: 101,
          resource_id: 7,
          resource_name: 'Salle Rhône',
          title: null,
          starts_at: '2026-06-12T09:00:00+02:00',
          ends_at: '2026-06-12T13:00:00+02:00',
          status: 'confirmed',
          is_paid: true,
          ticket: { id: 55, type: 'meeting_room_half_day' },
          cancellable: true,
        },
      ],
    })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByText('nº 55 — Salle — demi-journée')).toBeInTheDocument()
  })
})

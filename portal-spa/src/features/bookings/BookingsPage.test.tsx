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

/**
 * L'agenda est rendu par FullCalendar (ADR-0013 D3). Ce qui est testé ici :
 * la barre d'outils, les chips de filtre, le popover, la modale de réservation
 * et la liste — c'est-à-dire tout ce que le membre peut atteindre au clavier.
 * Le **glisser** sur un créneau libre n'est pas simulable en jsdom (drag
 * pointer dans la grille) : la logique qu'il déclenche est couverte par
 * `calendarEvents.test.ts` (`rangeFromSelection`, contraintes external).
 */

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

const SECOND_ROOM = {
  ...MEETING_ROOM,
  id: 8,
  name: 'Salle Saône',
  capacity: 4,
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
      { ...SECOND_ROOM, slots: [] },
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
    http.get('/api/rooms', () =>
      HttpResponse.json({ data: [MEETING_ROOM, SECOND_ROOM, EVENT_ROOM] }),
    ),
    http.get('/api/rooms/availability', () => availability(slots)),
    http.get('/api/bookings', ({ request }) =>
      bookingsPage(new URL(request.url).searchParams.has('past') ? pastBookings : bookings),
    ),
  )
}

/** Créneau d'un autre membre, avec occupant et libellé (Q4). */
const OTHER_SLOT = {
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
}

/** Sa propre réservation, encore annulable. */
const MY_SLOT = {
  booking_id: 42,
  is_mine: true,
  cancellable: true,
  starts_at: '2026-06-12T14:00:00+02:00',
  ends_at: '2026-06-12T15:00:00+02:00',
  label: 'Point équipe',
  occupant: null,
}

describe('BookingsPage — agenda des salles', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(TODAY)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('affiche la barre d’outils, les chips de salles et le panneau droit', async () => {
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(await screen.findByRole('button', { name: 'Nouvelle réservation' })).toBeVisible()
    // Semaine du lundi 8 juin 2026 (la date de référence est un mercredi).
    expect(screen.getByText('8 – 14 juin 2026')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aujourd’hui' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Période précédente' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Période suivante' })).toBeInTheDocument()

    // Sélecteur de vue : semaine active par défaut en desktop.
    const views = screen.getByRole('radiogroup', { name: 'Affichage du calendrier' })
    expect(within(views).getByRole('radio', { name: 'Semaine' })).toBeChecked()

    // Une chip par salle (salle event incluse), toutes affichées au départ.
    expect(screen.getByRole('button', { name: /Salle Rhône/ })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    expect(screen.getByRole('button', { name: /Salle événementielle — affichée/ })).toBeVisible()
    expect(screen.getByRole('button', { name: 'Voir 24 h' })).toHaveAttribute(
      'aria-pressed',
      'false',
    )

    // Panneau droit : mini-mois, renvoi vers la liste, abonnement iCal.
    expect(screen.getByRole('link', { name: /Voir toutes mes réservations/ })).toBeVisible()
    expect(screen.getByRole('heading', { name: 'Abonnement agenda' })).toBeVisible()
  })

  it('francise les libellés de navigation du mini-mois', async () => {
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    // `react-day-picker` laisse « Go to the Previous Month » en dur même avec
    // `locale={fr}` : les libellés sont surchargés dans `ui/calendar.tsx`.
    await screen.findByRole('button', { name: 'Nouvelle réservation' })
    expect(screen.getByRole('button', { name: 'Mois précédent' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Mois suivant' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Go to the/ })).not.toBeInTheDocument()
  })

  it('écrit le nom de la salle dans le bloc, jamais la couleur seule', async () => {
    setup({ slots: [OTHER_SLOT] })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    // Titre (libellé), puis « horaire · salle », puis occupant · entité.
    const title = await screen.findByText('Comité produit')
    const block = title.closest('.ew-ev-content')
    expect(block).not.toBeNull()
    expect(within(block as HTMLElement).getByText(/Salle Rhône/)).toBeVisible()
    expect(within(block as HTMLElement).getByText('Hugo Discret (Atelier Numérique)')).toBeVisible()
  })

  it('ferme le popover dès que la grille défile sous lui', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({ slots: [OTHER_SLOT] })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByText('Comité produit'))
    expect(await screen.findByRole('dialog')).toBeVisible()

    // Le popover est ancré à un rectangle figé au clic : il doit disparaître
    // plutôt que de pointer à côté.
    window.dispatchEvent(new Event('scroll'))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('annonce l’alternative accessible avant la grille (ADR-0013 D4)', async () => {
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    expect(
      await screen.findByText(/n’est pas navigable au clavier/, { selector: 'p' }),
    ).toBeInTheDocument()
  })

  it('navigue vers la période suivante et redemande la plage correspondante', async () => {
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
    await screen.findByRole('button', { name: 'Nouvelle réservation' })
    await waitFor(() => expect(ranges).toContain('2026-06-08→2026-06-14'))

    await user.click(screen.getByRole('button', { name: 'Période suivante' }))

    await waitFor(() => expect(ranges).toContain('2026-06-15→2026-06-21'))
    expect(screen.getByText('15 – 21 juin 2026')).toBeInTheDocument()
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

    const views = await screen.findByRole('radiogroup', { name: 'Affichage du calendrier' })
    expect(within(views).getByRole('radio', { name: 'Jour' })).toBeChecked()
    expect(screen.getByText('mercredi 10 juin 2026')).toBeInTheDocument()
  })

  it('bascule sur la vue mois via le sélecteur de vue', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('button', { name: 'Nouvelle réservation' })

    await user.click(screen.getByRole('radio', { name: 'Mois' }))

    // Le libellé de période, pas la légende du mini-mois du panneau droit.
    expect(await screen.findByText('juin 2026', { selector: 'p' })).toBeInTheDocument()
  })

  it('étend la plage horaire à 24 h via le toggle', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    const { container } = renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('button', { name: 'Nouvelle réservation' })

    // 8 h-20 h par défaut : pas de créneau de minuit dans la grille.
    expect(container.querySelector('[data-time="00:00:00"]')).toBeNull()
    expect(container.querySelector('[data-time="08:00:00"]')).not.toBeNull()

    await user.click(screen.getByRole('button', { name: 'Voir 24 h' }))

    await waitFor(() => expect(container.querySelector('[data-time="00:00:00"]')).not.toBeNull())
    expect(container.querySelector('[data-time="23:00:00"]')).not.toBeNull()
  })

  it('masque une salle via sa chip et ne la demande plus au serveur', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const requested: (string | null)[] = []
    setup()
    server.use(
      http.get('/api/rooms/availability', ({ request }) => {
        requested.push(new URL(request.url).searchParams.getAll('rooms[]').join(','))
        return availability()
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByRole('button', { name: 'Nouvelle réservation' })
    await waitFor(() => expect(requested).toContain('7,8,9'))

    await user.click(screen.getByRole('button', { name: /Salle Saône/ }))

    await waitFor(() => expect(requested).toContain('7,9'))
    expect(screen.getByRole('button', { name: /Salle Saône/ })).toHaveAttribute(
      'aria-pressed',
      'false',
    )
  })

  it('montre l’occupant, l’entité et le libellé d’une résa d’un autre membre, sans action', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({ slots: [OTHER_SLOT] })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByText('Comité produit'))

    // Popover de détail : occupant + entité (Q4, transparence entre membres).
    // Scopé au popover : le bloc de la grille affiche déjà ces informations.
    const popover = await screen.findByRole('dialog')
    expect(within(popover).getByText('Hugo Discret (Atelier Numérique)')).toBeVisible()
    expect(within(popover).getByText(/Salle Rhône · 6 places/)).toBeVisible()
    // Résa d'un autre membre : jamais modifiable ni annulable.
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Annuler' })).not.toBeInTheDocument()
  })

  it('ouvre « Modifier » depuis le popover de sa propre réservation', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const patched = vi.fn()
    setup({ slots: [MY_SLOT] })
    server.use(
      http.patch('/api/bookings/42', async ({ request }) => {
        patched(await request.json())
        return HttpResponse.json({ data: { id: 42 } })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByText('Point équipe'))
    await user.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = await screen.findByRole('dialog', { name: 'Ma réservation — Salle Rhône' })
    expect(within(dialog).getByLabelText('Salle')).toHaveValue('7')
    expect(within(dialog).getByLabelText('Libellé (optionnel)')).toHaveValue('Point équipe')
    await user.clear(within(dialog).getByLabelText('Heure de fin'))
    await user.type(within(dialog).getByLabelText('Heure de fin'), '16:00')
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer les modifications' }))

    await waitFor(() => expect(patched).toHaveBeenCalledTimes(1))
    const payload = patched.mock.calls[0]?.[0] as { ends_at: string }
    expect(new Date(payload.ends_at).getHours()).toBe(16)
    expect(await screen.findByText('Réservation modifiée.')).toBeVisible()
  })

  it('annule sa réservation depuis le popover, après confirmation', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const deleted = vi.fn()
    setup({ slots: [MY_SLOT] })
    server.use(
      http.delete('/api/bookings/42', () => {
        deleted()
        return HttpResponse.json({ message: 'Réservation annulée.' })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByText('Point équipe'))
    await user.click(await screen.findByRole('button', { name: 'Annuler' }))
    // La confirmation est un AlertDialog Radix rendu dans un portail, hors du
    // DOM du popover.
    await user.click(await screen.findByRole('button', { name: 'Oui, annuler' }))

    await waitFor(() => expect(deleted).toHaveBeenCalledTimes(1))
    expect(await screen.findByText('Réservation annulée.')).toBeVisible()
  })

  it('propose de nous contacter pour la salle événementielle', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: 'Salle événementielle : sur demande' }),
    )

    expect(await screen.findByText('Pour réserver cette salle, contactez-nous.')).toBeVisible()
    expect(screen.getByRole('link', { name: 'Nous contacter' })).toHaveAttribute(
      'href',
      'mailto:contact@ecoworking.fr?subject=[backend ecowo] Réservation salle événementielle',
    )
    // Aucune modale de réservation ne s'ouvre pour la salle event.
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renvoie le même message au clic sur un créneau de la salle événementielle', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    server.use(
      http.get('/api/rooms/availability', () =>
        availability(
          [],
          [
            {
              booking_id: null,
              is_mine: false,
              cancellable: false,
              starts_at: '2026-06-11T18:00:00+02:00',
              ends_at: '2026-06-11T20:00:00+02:00',
              label: 'Afterwork',
              occupant: null,
            },
          ],
        ),
      ),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByText('Afterwork'))

    expect(await screen.findByText('Pour réserver cette salle, contactez-nous.')).toBeVisible()
  })
})

describe('BookingsPage — modale de réservation', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.setSystemTime(TODAY)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('réserve depuis « Nouvelle réservation » : salle, date et heures saisies', async () => {
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

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))

    const dialog = await screen.findByRole('dialog', { name: 'Nouvelle réservation' })
    // Créneau par défaut : aujourd'hui, prochaine heure pleine.
    expect(within(dialog).getByLabelText('Date')).toHaveValue('2026-06-10')
    expect(within(dialog).getByLabelText('Heure de début')).toHaveValue('10:00')
    // Salle à choisir : la salle événementielle n'est pas proposée (§3.5.4).
    const roomSelect = within(dialog).getByLabelText('Salle')
    expect(within(roomSelect).queryByRole('option', { name: /Salle événementielle/ })).toBeNull()

    await user.selectOptions(roomSelect, '8')
    await user.type(within(dialog).getByLabelText('Libellé (optionnel)'), 'Point équipe')
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    expect(created.mock.calls[0]?.[0]).toMatchObject({ resource_id: 8, title: 'Point équipe' })
    expect(await screen.findByText('Réservation confirmée.')).toBeVisible()
  })

  it('refuse la soumission sans salle choisie', async () => {
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

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))
    const dialog = await screen.findByRole('dialog', { name: 'Nouvelle réservation' })
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    expect(await within(dialog).findByText('La salle est requise.')).toBeVisible()
    expect(created).not.toHaveBeenCalled()
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

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Salle'), '7')
    await user.click(within(dialog).getByLabelText('Matin (9 h – 13 h)'))
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    const payload = created.mock.calls[0]?.[0] as { starts_at: string; ends_at: string }
    expect(new Date(payload.starts_at).getHours()).toBe(9)
    expect(new Date(payload.ends_at).getHours()).toBe(13)
  })

  it('affiche le conflit 409 et propose le créneau libre le plus proche', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup()
    server.use(
      http.post('/api/bookings', () =>
        HttpResponse.json(
          { message: 'Ce créneau est déjà réservé pour cette salle.' },
          { status: 409 },
        ),
      ),
      // La suggestion se calcule sur une dispo FRAÎCHE : 10:00–11:00 vient
      // d'être pris par un tiers, 11:00–12:00 l'était déjà.
      http.get('/api/rooms/:id/availability', () =>
        HttpResponse.json({
          date: '2026-06-10',
          busy: [
            { starts_at: '2026-06-10T10:00:00+02:00', ends_at: '2026-06-10T11:00:00+02:00' },
            { starts_at: '2026-06-10T11:00:00+02:00', ends_at: '2026-06-10T12:00:00+02:00' },
          ],
          external_slots: [],
          is_external: false,
        }),
      ),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Salle'), '7')
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    expect(
      await within(dialog).findByText('Ce créneau est déjà réservé pour cette salle.'),
    ).toBeVisible()
    expect(await within(dialog).findByText(/Créneau libre le plus proche/)).toBeVisible()
    expect(within(dialog).getByRole('button', { name: '12:00 – 13:00' })).toBeVisible()
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

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Salle'), '7')
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

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))
    expect(await screen.findByRole('dialog')).toBeVisible()

    await user.keyboard('{Escape}')

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('affiche la modale en lecture seule si l’état de la liste est périmé', async () => {
    setup()
    renderWithProviders(
      <BookingDialog
        target={{
          mode: 'edit',
          bookingId: 77,
          roomId: 7,
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

    expect(
      await screen.findByRole('dialog', { name: 'Ma réservation — Salle Rhône' }),
    ).toBeVisible()
    expect(screen.getByText(/n’est plus modifiable ni annulable depuis le portail/)).toBeVisible()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Enregistrer les modifications' }),
    ).not.toBeInTheDocument()
    expect(screen.getByText('Déjà commencée')).toBeVisible()
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

  const UPCOMING = {
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
  }

  it('liste les prochaines réservations par défaut et bascule sur l’historique', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({
      bookings: [UPCOMING],
      pastBookings: [
        {
          ...UPCOMING,
          id: 90,
          title: 'Rétro',
          starts_at: '2026-05-12T10:00:00+02:00',
          ends_at: '2026-05-12T11:00:00+02:00',
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

  it('n’offre aucune action sur une résa déjà commencée', async () => {
    setup({ bookings: [{ ...UPCOMING, title: 'Déjà commencée', cancellable: false }] })
    renderWithProviders(<BookingsPage />, { withAuth: true })
    await screen.findByText('Déjà commencée')

    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
  })

  it('modifie puis supprime une réservation depuis la liste', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const deleted = vi.fn()
    setup({ bookings: [UPCOMING] })
    server.use(
      http.patch('/api/bookings/100', () => HttpResponse.json({ data: { id: 100 } })),
      http.delete('/api/bookings/100', () => {
        deleted()
        return HttpResponse.json({ message: 'Réservation annulée.' })
      }),
    )
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /Modifier/ }))
    const dialog = await screen.findByRole('dialog', { name: 'Ma réservation — Salle Rhône' })
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer les modifications' }))
    // Sonner rend le toast deux fois (région live + visuel) : on ne cible pas
    // un nœud unique.
    expect((await screen.findAllByText('Réservation modifiée.')).length).toBeGreaterThan(0)

    await user.click(await screen.findByRole('button', { name: /Modifier/ }))
    const again = await screen.findByRole('dialog', { name: 'Ma réservation — Salle Rhône' })
    await user.click(within(again).getByRole('button', { name: 'Supprimer' }))
    await user.click(await screen.findByRole('button', { name: 'Oui, supprimer' }))

    await waitFor(() => expect(deleted).toHaveBeenCalledTimes(1))
    expect((await screen.findAllByText('Réservation annulée.')).length).toBeGreaterThan(0)
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

    await screen.findByRole('button', { name: 'Nouvelle réservation' })
    expect(screen.queryByRole('button', { name: 'Voir 24 h' })).not.toBeInTheDocument()
    expect(screen.getByText(/Tickets salle de réunion disponibles/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Nouvelle réservation' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).queryByLabelText('Créneau personnalisé')).not.toBeInTheDocument()
    expect(within(dialog).queryByLabelText('Heure de début')).not.toBeInTheDocument()

    await user.selectOptions(within(dialog).getByLabelText('Salle'), '7')
    await user.click(within(dialog).getByLabelText('Après-midi (14 h – 18 h)'))
    await user.click(within(dialog).getByRole('button', { name: 'Réserver' }))

    await waitFor(() => expect(created).toHaveBeenCalledTimes(1))
    expect(created.mock.calls[0]?.[0]).toMatchObject({
      resource_id: 7,
      date: '2026-06-10',
      period: 'afternoon',
    })
  })

  it('invite à contacter Ecoworking quand le solde de tickets salle est nul', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    setup({ permissions: EXTERNAL_PERMISSIONS, meetingTickets: 0 })
    renderWithProviders(<BookingsPage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: 'Nouvelle réservation' }))

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

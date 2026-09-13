import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { usePermissions } from '@/features/auth/usePermissions'
import { CalendarSubscription } from '@/features/calendar/CalendarSubscription'
import { useTickets } from '@/features/tickets/useTickets'
import { usePageTitle } from '@/lib/usePageTitle'
import { BookingDialog, type DialogTarget } from './BookingDialog'
import { MyBookingsList } from './MyBookingsList'
import { type PickedSlot, RoomsCalendar } from './RoomsCalendar'
import type { Booking } from './types'

/** PRD §3.5.4 / §3.5.6 : pas d'achat en ligne en MVP, on invite à écrire. */
const EVENT_ROOM_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Réservation salle événementielle'
const TICKETS_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Tickets salle de réunion'

type Notice = { kind: 'success' | 'info'; message: string; mailto?: string } | null

export function BookingsPage() {
  usePageTitle('Réservations — Portail Ecoworking')

  const { isExternal } = usePermissions()
  const tickets = useTickets()
  const [notice, setNotice] = useState<Notice>(null)
  const [target, setTarget] = useState<DialogTarget | null>(null)

  const roomTickets = tickets.data?.balances.meeting_room_half_day ?? null

  function onPick(picked: PickedSlot) {
    setNotice(null)

    // Sa propre réservation → modale « Modifier / Supprimer » (PRD §3.5.5).
    if (picked.slot !== null && picked.slot.booking_id !== null) {
      setTarget({
        mode: 'edit',
        bookingId: picked.slot.booking_id,
        roomId: picked.room.id,
        roomName: picked.room.name,
        startsAt: picked.slot.starts_at,
        endsAt: picked.slot.ends_at,
        title: picked.slot.label,
        // Le serveur tranche : une résa déjà commencée n'est plus modifiable
        // (délai Q22) — la modale s'ouvre alors en lecture seule.
        cancellable: picked.slot.cancellable,
      })
      return
    }

    // External sans ticket : on le dit avant de proposer le formulaire (§3.5.3).
    if (isExternal && roomTickets === 0) {
      setNotice({
        kind: 'info',
        message:
          'Vous n’avez plus de ticket salle de réunion. Contactez Ecoworking pour en obtenir.',
        mailto: TICKETS_MAILTO,
      })
      return
    }

    setTarget({
      mode: 'create',
      roomId: picked.room.id,
      roomName: picked.room.name,
      date: picked.date,
      startHour: picked.hour,
    })
  }

  function onEditFromList(booking: Booking) {
    setNotice(null)
    setTarget({
      mode: 'edit',
      bookingId: booking.id,
      roomId: booking.resource_id,
      roomName: booking.resource_name,
      startsAt: booking.starts_at,
      endsAt: booking.ends_at,
      title: booking.title,
      cancellable: booking.cancellable,
    })
  }

  return (
    <div className="mx-auto max-w-5xl space-y-10">
      <h1 className="text-2xl font-semibold">Réservations</h1>

      {notice !== null && (
        <Alert variant={notice.kind === 'success' ? 'success' : 'info'}>
          {notice.message}
          {notice.mailto !== undefined && (
            <a
              href={notice.mailto}
              className="ml-2 font-medium underline underline-offset-2 hover:no-underline"
            >
              Nous contacter
            </a>
          )}
        </Alert>
      )}

      {isExternal && roomTickets !== null && (
        <p className="text-sm text-neutral-600 dark:text-neutral-300">
          Tickets salle de réunion disponibles : <strong>{roomTickets}</strong>
        </p>
      )}

      <RoomsCalendar
        isExternal={isExternal}
        onPick={onPick}
        onPickEventRoom={() =>
          setNotice({
            kind: 'info',
            message: 'Pour réserver cette salle, contactez-nous.',
            mailto: EVENT_ROOM_MAILTO,
          })
        }
      />

      <BookingDialog
        target={target}
        isExternal={isExternal}
        onClose={() => setTarget(null)}
        onSuccess={(message) => setNotice({ kind: 'success', message })}
      />

      <MyBookingsList isExternal={isExternal} onEdit={onEditFromList} />

      <CalendarSubscription />
    </div>
  )
}

import { fr } from 'date-fns/locale'
import { useState } from 'react'
import { toast } from 'sonner'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { Alert } from '@/components/ui/alert'
import { Calendar } from '@/components/ui/calendar'
import { Card, CardContent } from '@/components/ui/card'
import { usePermissions } from '@/features/auth/usePermissions'
import { CalendarSubscription } from '@/features/calendar/CalendarSubscription'
import { useTickets } from '@/features/tickets/useTickets'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { BookingDialog, type DialogTarget } from './BookingDialog'
import { atHour } from './calendar'
import { defaultRange, type EventSlotProps } from './calendarEvents'
import { MyBookingsList } from './MyBookingsList'
import { type PickedSlotRange, RoomsCalendar } from './RoomsCalendar'
import type { Booking } from './types'
import { useCancelBooking } from './useBookings'

/** PRD §3.5.4 / §3.5.6 : pas d'achat en ligne en MVP, on invite à écrire. */
const EVENT_ROOM_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Réservation salle événementielle'
const TICKETS_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Tickets salle de réunion'

// Messages persistants (nécessitent un lien mailto visible) : Alert, pas un
// toast éphémère. Le succès d'une réservation, lui, est un toast (PRD §3.1).
type Notice = { message: string; mailto?: string } | null

export function BookingsPage() {
  usePageTitle('Réservations — Portail Ecoworking')

  const { isExternal } = usePermissions()
  const tickets = useTickets()
  const cancelBooking = useCancelBooking()
  const [notice, setNotice] = useState<Notice>(null)
  const [target, setTarget] = useState<DialogTarget | null>(null)
  const [date, setDate] = useState<Date>(() => atHour(new Date(), 0))

  const roomTickets = tickets.data?.balances.meeting_room_half_day ?? null

  /** External sans ticket : on le dit avant de proposer le formulaire (§3.5.3). */
  function blockedByTickets(): boolean {
    if (isExternal && roomTickets === 0) {
      setNotice({
        message:
          'Vous n’avez plus de ticket salle de réunion. Contactez Ecoworking pour en obtenir.',
        mailto: TICKETS_MAILTO,
      })
      return true
    }
    return false
  }

  /** Glisser sur un créneau libre de l'agenda : salle et créneau pré-remplis. */
  function onPickRange(range: PickedSlotRange) {
    setNotice(null)
    if (blockedByTickets()) {
      return
    }
    setTarget({
      mode: 'create',
      roomId: range.roomId,
      date: range.date,
      startTime: range.startTime,
      endTime: range.endTime,
    })
  }

  /** Bouton « Nouvelle réservation » : saisie manuelle intégrale (ADR-0013 D4). */
  function onNewBooking() {
    setNotice(null)
    if (blockedByTickets()) {
      return
    }
    setTarget({ mode: 'create', roomId: null, ...defaultRange() })
  }

  /** « Modifier » depuis le popover de l'agenda (sa propre réservation). */
  function onEditFromCalendar(picked: EventSlotProps) {
    setNotice(null)
    if (picked.slot.booking_id === null) {
      return
    }
    setTarget({
      mode: 'edit',
      bookingId: picked.slot.booking_id,
      roomId: picked.roomId,
      startsAt: picked.slot.starts_at,
      endsAt: picked.slot.ends_at,
      title: picked.slot.label,
      // Le serveur tranche : une résa déjà commencée n'est plus modifiable
      // (délai Q22) — la modale s'ouvre alors en lecture seule.
      cancellable: picked.slot.cancellable,
    })
  }

  function onEditFromList(booking: Booking) {
    setNotice(null)
    setTarget({
      mode: 'edit',
      bookingId: booking.id,
      roomId: booking.resource_id,
      startsAt: booking.starts_at,
      endsAt: booking.ends_at,
      title: booking.title,
      cancellable: booking.cancellable,
    })
  }

  /** « Annuler » depuis le popover : la confirmation a déjà été donnée. */
  function onCancelFromCalendar(bookingId: number) {
    setNotice(null)
    cancelBooking.mutate(bookingId, {
      onSuccess: () => toast.success('Réservation annulée.'),
      onError: (error) => toast.error(getApiErrorMessage(error, 'Annulation impossible.')),
    })
  }

  return (
    <PageContainer width="full" className="space-y-6">
      <PageHeader title="Réservations de salles" />

      {notice !== null && (
        <Alert variant="info">
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
        <p className="text-sm text-muted-foreground">
          Tickets salle de réunion disponibles : <strong>{roomTickets}</strong>
        </p>
      )}

      <div className="flex flex-col gap-5 lg:flex-row lg:items-start">
        <Card className="min-w-0 flex-1 px-4">
          <RoomsCalendar
            isExternal={isExternal}
            date={date}
            onDateChange={setDate}
            onPickRange={onPickRange}
            onNewBooking={onNewBooking}
            onEditBooking={onEditFromCalendar}
            onCancelBooking={onCancelFromCalendar}
            onPickEventRoom={() =>
              setNotice({
                message: 'Pour réserver cette salle, contactez-nous.',
                mailto: EVENT_ROOM_MAILTO,
              })
            }
          />
        </Card>

        {/* Panneau droit (maquette C14) : navigation par mini-mois, rappel de
            l'alternative accessible, abonnement iCal. */}
        <aside className="flex w-full shrink-0 flex-col gap-4 lg:w-[300px]">
          <Card className="px-2">
            <CardContent className="px-0">
              <Calendar
                mode="single"
                locale={fr}
                weekStartsOn={1}
                selected={date}
                month={date}
                onMonthChange={setDate}
                onSelect={(next) => next !== undefined && setDate(next)}
                className="w-full [--cell-size:--spacing(8)]"
              />
            </CardContent>
          </Card>

          <Card className="px-4">
            <CardContent className="space-y-2 px-0 text-sm">
              <p className="font-medium">Mes réservations</p>
              <p className="text-muted-foreground">
                La liste complète (à venir et historique) permet de consulter, modifier ou annuler
                vos réservations sans passer par l’agenda.
              </p>
              <a
                href="#my-bookings-heading"
                className="inline-block font-medium text-primary underline underline-offset-2 hover:no-underline"
              >
                Voir toutes mes réservations (liste)
              </a>
            </CardContent>
          </Card>

          <Card className="px-4">
            <CardContent className="px-0">
              <CalendarSubscription />
            </CardContent>
          </Card>
        </aside>
      </div>

      <BookingDialog
        target={target}
        isExternal={isExternal}
        onClose={() => setTarget(null)}
        onSuccess={(message) => toast.success(message)}
      />

      <MyBookingsList isExternal={isExternal} onEdit={onEditFromList} />
    </PageContainer>
  )
}

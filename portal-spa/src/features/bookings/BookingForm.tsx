import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Spinner } from '@/components/ui/Spinner'
import { getApiErrorMessage } from '@/lib/errors'
import type { ExternalSlot, SlotPeriod } from './types'
import { useCreateBooking, useRoomAvailability, useRooms } from './useBookings'

const PERIOD_LABELS: Record<SlotPeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
}

const HOURS = Array.from({ length: 12 }, (_, i) => 8 + i) // 08:00 → 19:00

/** Date du jour au format YYYY-MM-DD (fuseau local). */
function todayIso(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  return new Date(now.getTime() - offset * 60_000).toISOString().slice(0, 10)
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

/** Compose une date ISO8601 locale à partir d'un jour et d'une heure. */
function isoFor(date: string, hour: number): string {
  const d = new Date(`${date}T${String(hour).padStart(2, '0')}:00:00`)
  return d.toISOString()
}

/** Indique si un créneau [start,end[ chevauche une plage occupée. */
function overlaps(
  startIso: string,
  endIso: string,
  busy: { starts_at: string; ends_at: string }[],
) {
  const start = new Date(startIso).getTime()
  const end = new Date(endIso).getTime()
  return busy.some((slot) => {
    const bStart = new Date(slot.starts_at).getTime()
    const bEnd = new Date(slot.ends_at).getTime()
    return start < bEnd && end > bStart
  })
}

interface BookingFormProps {
  /** External = réservation à la demi-journée payante ; sinon créneau horaire. */
  isExternal: boolean
  onBooked: () => void
}

export function BookingForm({ isExternal, onBooked }: BookingFormProps) {
  const { data: rooms, isLoading: roomsLoading, isError: roomsError } = useRooms()
  const [roomId, setRoomId] = useState<number | null>(null)
  const [date, setDate] = useState<string>(todayIso())
  const [title, setTitle] = useState('')
  const [feedback, setFeedback] = useState<{ type: 'error' | 'success'; message: string } | null>(
    null,
  )

  const availability = useRoomAvailability(roomId, date)
  const createBooking = useCreateBooking()

  const selectedRoom = rooms?.find((room) => room.id === roomId) ?? null

  function resetFeedback() {
    setFeedback(null)
  }

  async function bookSlot(
    payload: { starts_at: string; ends_at: string } | { period: SlotPeriod },
  ) {
    if (roomId === null) return
    resetFeedback()
    try {
      await createBooking.mutateAsync(
        'period' in payload
          ? { resource_id: roomId, date, period: payload.period, title: title || undefined }
          : {
              resource_id: roomId,
              starts_at: payload.starts_at,
              ends_at: payload.ends_at,
              title: title || undefined,
            },
      )
      setFeedback({ type: 'success', message: 'Réservation confirmée.' })
      setTitle('')
      onBooked()
    } catch (error) {
      setFeedback({ type: 'error', message: getApiErrorMessage(error, 'Réservation impossible.') })
    }
  }

  if (roomsLoading) {
    return <Spinner label="Chargement des salles…" />
  }
  if (roomsError || !rooms) {
    return <Alert variant="error">Impossible de charger les salles.</Alert>
  }
  if (rooms.length === 0) {
    return <Alert variant="info">Aucune salle réservable pour le moment.</Alert>
  }

  return (
    <section aria-labelledby="booking-form-heading" className="space-y-4">
      <h2 id="booking-form-heading" className="text-lg font-medium">
        Réserver une salle
      </h2>

      {feedback && (
        <Alert variant={feedback.type === 'success' ? 'success' : 'error'}>
          {feedback.message}
        </Alert>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="booking-room">Salle</Label>
          <Select
            id="booking-room"
            value={roomId ?? ''}
            onChange={(event) => {
              resetFeedback()
              setRoomId(event.target.value === '' ? null : Number(event.target.value))
            }}
          >
            <option value="">Choisir une salle…</option>
            {rooms.map((room) => (
              <option key={room.id} value={room.id}>
                {room.name}
                {room.capacity ? ` (${room.capacity} pers.)` : ''}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <Label htmlFor="booking-date">Date</Label>
          <Input
            id="booking-date"
            type="date"
            min={todayIso()}
            value={date}
            onChange={(event) => {
              resetFeedback()
              setDate(event.target.value)
            }}
          />
        </div>
      </div>

      <div>
        <Label htmlFor="booking-title">Intitulé (optionnel)</Label>
        <Input
          id="booking-title"
          value={title}
          maxLength={255}
          placeholder="Réunion équipe…"
          onChange={(event) => setTitle(event.target.value)}
        />
      </div>

      {selectedRoom?.description && (
        <p className="text-sm text-neutral-500">{selectedRoom.description}</p>
      )}

      {roomId !== null && (
        <div aria-live="polite">
          {availability.isLoading && <Spinner label="Chargement des disponibilités…" />}
          {availability.isError && (
            <Alert variant="error">Impossible de charger les disponibilités.</Alert>
          )}
          {availability.data &&
            (isExternal ? (
              <ExternalSlotList
                slots={availability.data.external_slots}
                busy={availability.data.busy}
                onBook={(slot) => void bookSlot({ period: slot.period })}
                pending={createBooking.isPending}
              />
            ) : (
              <AgendaSlotList
                date={date}
                busy={availability.data.busy}
                onBook={(start, end) => void bookSlot({ starts_at: start, ends_at: end })}
                pending={createBooking.isPending}
              />
            ))}
        </div>
      )}
    </section>
  )
}

/**
 * Vue agenda accessible (liste de créneaux d'1h) — alternative a11y au calendrier
 * graphique exigée par CLAUDE.md §3.5.
 */
function AgendaSlotList({
  date,
  busy,
  onBook,
  pending,
}: {
  date: string
  busy: { starts_at: string; ends_at: string }[]
  onBook: (startIso: string, endIso: string) => void
  pending: boolean
}) {
  return (
    <div className="rounded-lg border border-neutral-200 dark:border-neutral-800">
      <h3 className="border-b border-neutral-200 px-4 py-2 text-sm font-medium dark:border-neutral-800">
        Créneaux du{' '}
        {new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
        })}
      </h3>
      <ul className="divide-y divide-neutral-100 dark:divide-neutral-800">
        {HOURS.map((hour) => {
          const startIso = isoFor(date, hour)
          const endIso = isoFor(date, hour + 1)
          const taken = overlaps(startIso, endIso, busy)
          const label = `${String(hour).padStart(2, '0')}:00 – ${String(hour + 1).padStart(2, '0')}:00`
          return (
            <li key={hour} className="flex items-center justify-between px-4 py-2">
              <span className="text-sm tabular-nums">{label}</span>
              {taken ? (
                <span className="text-sm text-neutral-400">Occupé</span>
              ) : (
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={pending}
                  onClick={() => onBook(startIso, endIso)}
                >
                  Réserver<span className="sr-only"> le créneau {label}</span>
                </Button>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

/** Liste des demi-journées payantes proposées aux externals. */
function ExternalSlotList({
  slots,
  busy,
  onBook,
  pending,
}: {
  slots: ExternalSlot[]
  busy: { starts_at: string; ends_at: string }[]
  onBook: (slot: ExternalSlot) => void
  pending: boolean
}) {
  if (slots.length === 0) {
    return <Alert variant="info">Aucun créneau disponible pour cette date.</Alert>
  }

  return (
    <div className="rounded-lg border border-neutral-200 dark:border-neutral-800">
      <h3 className="border-b border-neutral-200 px-4 py-2 text-sm font-medium dark:border-neutral-800">
        Demi-journées disponibles
      </h3>
      <ul className="divide-y divide-neutral-100 dark:divide-neutral-800">
        {slots.map((slot) => {
          const taken = overlaps(slot.starts_at, slot.ends_at, busy)
          const label = `${PERIOD_LABELS[slot.period]} (${formatTime(slot.starts_at)} – ${formatTime(slot.ends_at)})`
          return (
            <li key={slot.period} className="flex items-center justify-between px-4 py-2">
              <span className="text-sm">{label}</span>
              {taken ? (
                <span className="text-sm text-neutral-400">Occupé</span>
              ) : (
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={pending}
                  onClick={() => onBook(slot)}
                >
                  Réserver<span className="sr-only"> {label}</span>
                </Button>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

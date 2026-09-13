import { useState } from 'react'
import { QueryError } from '@/components/QueryError'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Spinner } from '@/components/ui/Spinner'
import {
  addDays,
  atHour,
  type CalendarView,
  DEFAULT_HOURS,
  describeSlot,
  EXTERNAL_HOURS,
  FULL_DAY_HOURS,
  formatDayLabel,
  formatHour,
  formatShortDay,
  hourRange,
  slotCovering,
  slotsOfDay,
  startOfWeek,
  toIsoDate,
  weekDays,
} from './calendar'
import type { CalendarRoom, CalendarSlot } from './types'
import { useRooms, useRoomsAvailability } from './useBookings'

/** Palette fixe par salle (la couleur configurable en admin est hors MVP). */
const ROOM_COLORS = [
  'border-sky-400 bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100',
  'border-amber-400 bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
  'border-violet-400 bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100',
  'border-teal-400 bg-teal-100 text-teal-900 dark:bg-teal-950 dark:text-teal-100',
] as const

/** Classes de couleur d'une salle (cycle sur la palette). */
function roomColor(index: number): string {
  return (
    ROOM_COLORS[((index % ROOM_COLORS.length) + ROOM_COLORS.length) % ROOM_COLORS.length] ??
    ROOM_COLORS[0]
  )
}

const MINE_CLASSES =
  'border-brand-600 bg-brand-600 text-white dark:border-brand-400 dark:bg-brand-700'

/** Créneau choisi dans la grille, transmis à la modale de réservation. */
export interface PickedSlot {
  room: CalendarRoom
  date: string
  hour: number
  /** Créneau occupé cliqué (sa propre résa) ; `null` sur un créneau libre. */
  slot: CalendarSlot | null
}

interface RoomsCalendarProps {
  isExternal: boolean
  /** Clic sur un créneau libre d'une salle réservable, ou sur SA réservation. */
  onPick: (slot: PickedSlot) => void
  /** Clic sur la salle événementielle (lecture seule, PRD §3.5.4). */
  onPickEventRoom: () => void
}

/** Pastille courte d'une salle : « 1 », « 2 »… et « EV » pour la salle event. */
function roomBadge(room: { type: string }, index: number): string {
  return room.type === 'event_room' ? 'EV' : String(index + 1)
}

function isMobileViewport(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
}

/**
 * Calendrier des salles (PRD §3.5.2) : toutes les salles affichées
 * simultanément, vue semaine (défaut desktop) ou jour (défaut mobile).
 *
 * Choix d'affichage : en vue **jour**, une colonne par salle — la lecture la
 * plus directe. En vue **semaine**, les colonnes sont les jours et chaque
 * cellule horaire empile une pastille par salle (couleur **et** numéro, jamais
 * la couleur seule — WCAG 1.4.1), avec une légende. La vue liste sous la
 * grille reste l'alternative accessible exigée par CLAUDE.md §3.5.
 */
export function RoomsCalendar({ isExternal, onPick, onPickEventRoom }: RoomsCalendarProps) {
  const [view, setView] = useState<CalendarView>(() => (isMobileViewport() ? 'day' : 'week'))
  const [anchor, setAnchor] = useState<Date>(() => atHour(new Date(), 0))
  const [showAllHours, setShowAllHours] = useState(false)
  const [selectedIds, setSelectedIds] = useState<number[] | null>(null)
  const [detail, setDetail] = useState<string | null>(null)

  const {
    data: catalog,
    isLoading: catalogLoading,
    isError: catalogError,
    refetch: refetchCatalog,
  } = useRooms()
  const roomIds = selectedIds ?? []

  const from = view === 'week' ? startOfWeek(anchor) : anchor
  const to = view === 'week' ? addDays(startOfWeek(anchor), 6) : anchor
  const days = view === 'week' ? weekDays(startOfWeek(anchor)) : [anchor]

  const availability = useRoomsAvailability(toIsoDate(from), toIsoDate(to), roomIds)

  const bounds = isExternal ? EXTERNAL_HOURS : showAllHours ? FULL_DAY_HOURS : DEFAULT_HOURS
  const hours = hourRange(bounds)
  const rooms = availability.data?.rooms ?? []

  const periodLabel =
    view === 'week'
      ? `Semaine du ${formatDayLabel(from)}`
      : formatDayLabel(anchor).replace(/^./, (c) => c.toUpperCase())

  function shift(direction: -1 | 1) {
    setAnchor((current) => addDays(current, direction * (view === 'week' ? 7 : 1)))
  }

  function toggleRoom(id: number, all: number[]) {
    setSelectedIds((current) => {
      const base = current ?? all
      return base.includes(id) ? base.filter((value) => value !== id) : [...base, id]
    })
  }

  if (catalogLoading) {
    return <Spinner label="Chargement des salles…" />
  }
  if (catalogError || !catalog) {
    return (
      <QueryError
        message="Impossible de charger les salles."
        onRetry={() => void refetchCatalog()}
      />
    )
  }
  if (catalog.length === 0) {
    return <Alert variant="info">Aucune salle n’est disponible pour le moment.</Alert>
  }

  const allIds = catalog.map((room) => room.id)
  const checkedIds = selectedIds ?? allIds

  return (
    <section aria-labelledby="rooms-calendar-heading" className="space-y-4">
      <h2 id="rooms-calendar-heading" className="text-lg font-medium">
        Calendrier des salles
      </h2>

      <div className="flex flex-wrap items-end gap-3">
        <nav aria-label="Navigation du calendrier" className="flex flex-wrap items-center gap-2">
          <Button variant="secondary" size="sm" onClick={() => shift(-1)}>
            {view === 'week' ? 'Semaine précédente' : 'Jour précédent'}
          </Button>
          <Button variant="secondary" size="sm" onClick={() => setAnchor(atHour(new Date(), 0))}>
            Aujourd’hui
          </Button>
          <Button variant="secondary" size="sm" onClick={() => shift(1)}>
            {view === 'week' ? 'Semaine suivante' : 'Jour suivant'}
          </Button>
        </nav>

        <div>
          <Label htmlFor="calendar-date">
            {view === 'week' ? 'Aller à la semaine du' : 'Aller au'}
          </Label>
          <Input
            id="calendar-date"
            type="date"
            value={toIsoDate(anchor)}
            onChange={(event) => {
              if (event.target.value !== '') {
                setAnchor(new Date(`${event.target.value}T00:00:00`))
              }
            }}
          />
        </div>

        <fieldset className="flex items-center gap-2">
          <legend className="sr-only">Affichage du calendrier</legend>
          <Button
            variant={view === 'week' ? 'primary' : 'secondary'}
            size="sm"
            aria-pressed={view === 'week'}
            onClick={() => setView('week')}
          >
            Semaine
          </Button>
          <Button
            variant={view === 'day' ? 'primary' : 'secondary'}
            size="sm"
            aria-pressed={view === 'day'}
            onClick={() => setView('day')}
          >
            Jour
          </Button>
        </fieldset>
      </div>

      <div className="flex flex-wrap items-center gap-4">
        <fieldset className="flex flex-wrap items-center gap-3">
          <legend className="sr-only">Salles affichées</legend>
          {catalog.map((room, index) => (
            <span key={room.id} className="inline-flex items-center gap-1.5 text-sm">
              <input
                id={`room-filter-${room.id}`}
                type="checkbox"
                className="size-4"
                checked={checkedIds.includes(room.id)}
                onChange={() => toggleRoom(room.id, allIds)}
              />
              <label
                htmlFor={`room-filter-${room.id}`}
                className="inline-flex items-center gap-1.5"
              >
                <span
                  aria-hidden="true"
                  className={`inline-flex size-5 items-center justify-center rounded border text-[0.7rem] font-semibold ${roomColor(index)}`}
                >
                  {roomBadge(room, index)}
                </span>
                {room.name}
                {!room.is_bookable && (
                  <span className="text-neutral-500 dark:text-neutral-400"> (lecture seule)</span>
                )}
              </label>
            </span>
          ))}
        </fieldset>

        {!isExternal && (
          <span className="inline-flex items-center gap-1.5 text-sm">
            <input
              id="calendar-all-hours"
              type="checkbox"
              className="size-4"
              checked={showAllHours}
              onChange={(event) => setShowAllHours(event.target.checked)}
            />
            <label htmlFor="calendar-all-hours">Voir 24 h</label>
          </span>
        )}
      </div>

      <p aria-live="polite" className="text-sm font-medium">
        {periodLabel}
      </p>

      {availability.isLoading && <Spinner label="Chargement du calendrier…" />}
      {availability.isError && (
        <QueryError
          message="Impossible de charger le calendrier des salles."
          onRetry={() => void availability.refetch()}
        />
      )}
      {checkedIds.length === 0 && (
        <Alert variant="info">Sélectionnez au moins une salle à afficher.</Alert>
      )}

      {availability.data && checkedIds.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
            <table className="w-full border-collapse text-sm">
              <caption className="sr-only">
                Occupation des salles — {periodLabel}. Chaque créneau indique la salle, son occupant
                et le libellé de la réservation.
              </caption>
              <thead>
                <tr className="bg-neutral-50 dark:bg-neutral-900">
                  <th scope="col" className="px-2 py-2 text-left font-medium">
                    Heure
                  </th>
                  {view === 'week'
                    ? days.map((day) => (
                        <th key={day.toISOString()} scope="col" className="px-2 py-2 font-medium">
                          {formatShortDay(day)}
                        </th>
                      ))
                    : rooms.map((room) => (
                        <th key={room.id} scope="col" className="px-2 py-2 font-medium">
                          {room.name}
                        </th>
                      ))}
                </tr>
              </thead>
              <tbody>
                {hours.map((hour) => (
                  <tr key={hour} className="border-t border-neutral-100 dark:border-neutral-800">
                    <th
                      scope="row"
                      className="whitespace-nowrap px-2 py-1 text-left font-normal tabular-nums text-neutral-600 dark:text-neutral-300"
                    >
                      {formatHour(hour)}
                    </th>
                    {view === 'week'
                      ? days.map((day) => (
                          <td key={day.toISOString()} className="px-1 py-1 align-top">
                            <div className="flex gap-0.5">
                              {rooms.map((room) => (
                                <SlotCell
                                  key={room.id}
                                  room={room}
                                  colorIndex={catalog.findIndex((item) => item.id === room.id)}
                                  badge={roomBadge(
                                    room,
                                    catalog.findIndex((item) => item.id === room.id),
                                  )}
                                  day={day}
                                  hour={hour}
                                  compact
                                  onPick={onPick}
                                  onPickEventRoom={onPickEventRoom}
                                  onDetail={setDetail}
                                />
                              ))}
                            </div>
                          </td>
                        ))
                      : rooms.map((room) => (
                          <td key={room.id} className="px-1 py-1 align-top">
                            <SlotCell
                              room={room}
                              colorIndex={catalog.findIndex((item) => item.id === room.id)}
                              badge={roomBadge(
                                room,
                                catalog.findIndex((item) => item.id === room.id),
                              )}
                              day={anchor}
                              hour={hour}
                              compact={false}
                              onPick={onPick}
                              onPickEventRoom={onPickEventRoom}
                              onDetail={setDetail}
                            />
                          </td>
                        ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Détail du créneau survolé ou atteint au clavier : l'infobulle
              flottante serait rognée par le défilement horizontal de la grille.
              `aria-hidden` — l'information est déjà dans l'`aria-label` du
              créneau, inutile de la faire annoncer deux fois. */}
          <p
            aria-hidden="true"
            className="min-h-10 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
          >
            {detail ?? 'Survolez un créneau (ou atteignez-le au clavier) pour en voir le détail.'}
          </p>

          <DayList rooms={rooms} days={days} />
        </>
      )}
    </section>
  )
}

interface SlotCellProps {
  room: CalendarRoom
  colorIndex: number
  badge: string
  day: Date
  hour: number
  compact: boolean
  onPick: (slot: PickedSlot) => void
  onPickEventRoom: () => void
  /** Remonte la description du créneau survolé / focalisé (panneau de détail). */
  onDetail: (description: string | null) => void
}

/** Une heure d'une salle : libre (réservable), occupée, ou passée. */
function SlotCell({
  room,
  colorIndex,
  badge,
  day,
  hour,
  compact,
  onPick,
  onPickEventRoom,
  onDetail,
}: SlotCellProps) {
  const start = atHour(day, hour)
  const end = atHour(day, hour + 1)
  const slot = slotCovering(room.slots, start, end)
  const isPast = end.getTime() <= Date.now()
  const color = roomColor(colorIndex)
  const size = compact ? 'h-7 flex-1 min-w-6 px-0.5 text-[0.7rem]' : 'h-9 w-full px-2 text-xs'
  const when = `${formatDayLabel(day)} ${formatHour(hour)}`

  if (slot !== null) {
    return (
      <BusySlotCell
        slot={slot}
        room={room}
        color={color}
        badge={badge}
        size={size}
        compact={compact}
        onPick={onPick}
        onDetail={onDetail}
        day={day}
        hour={hour}
      />
    )
  }

  if (!room.is_bookable) {
    return (
      <button
        type="button"
        className={`${size} rounded border border-dashed border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800`}
        aria-label={`${room.name}, ${when} : réservation sur demande auprès d’Ecoworking`}
        onClick={onPickEventRoom}
        onMouseEnter={() =>
          onDetail(`${room.name}, ${when} : réservation sur demande auprès d’Ecoworking`)
        }
        onMouseLeave={() => onDetail(null)}
        onFocus={() =>
          onDetail(`${room.name}, ${when} : réservation sur demande auprès d’Ecoworking`)
        }
        onBlur={() => onDetail(null)}
      >
        <span aria-hidden="true" className="block truncate">
          {compact ? badge : 'Sur demande'}
        </span>
      </button>
    )
  }

  if (isPast) {
    return (
      <span
        className={`${size} flex items-center justify-center rounded border border-neutral-200 bg-neutral-50 text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400`}
      >
        <span className="sr-only">{`${room.name}, ${when} : créneau passé`}</span>
        <span aria-hidden="true">—</span>
      </span>
    )
  }

  return (
    <button
      type="button"
      className={`${size} rounded border border-dashed border-neutral-300 text-neutral-600 hover:border-brand-600 hover:bg-brand-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800`}
      aria-label={`Réserver ${room.name}, ${when}`}
      onClick={() => onPick({ room, date: toIsoDate(day), hour, slot: null })}
      onMouseEnter={() => onDetail(`${room.name}, ${when} : libre`)}
      onMouseLeave={() => onDetail(null)}
      onFocus={() => onDetail(`${room.name}, ${when} : libre`)}
      onBlur={() => onDetail(null)}
    >
      <span aria-hidden="true" className="block truncate">
        {compact ? badge : 'Libre'}
      </span>
    </button>
  )
}

/**
 * Créneau occupé : les siennes sont mises en avant et — tant que le créneau n'a
 * pas commencé — modifiables. Celles des autres restent focalisables (leur
 * `aria-label` et le panneau de détail portent prénom + nom + entité + libellé,
 * Q4) mais ne sont jamais modifiables.
 */
function BusySlotCell({
  slot,
  room,
  color,
  badge,
  size,
  compact,
  onPick,
  onDetail,
  day,
  hour,
}: {
  slot: CalendarSlot
  room: CalendarRoom
  color: string
  badge: string
  size: string
  compact: boolean
  onPick: (picked: PickedSlot) => void
  onDetail: (description: string | null) => void
  day: Date
  hour: number
}) {
  const description = `${room.name}, ${formatDayLabel(day)} : ${describeSlot(slot)}`
  const editable = slot.is_mine && slot.booking_id !== null && slot.cancellable
  const classes = `${size} rounded border ${slot.is_mine ? MINE_CLASSES : color}`
  const detailHandlers = {
    onMouseEnter: () => onDetail(description),
    onMouseLeave: () => onDetail(null),
    onFocus: () => onDetail(description),
    onBlur: () => onDetail(null),
  }

  const content = (
    <span aria-hidden="true" className="block truncate">
      {compact ? badge : describeSlot(slot)}
    </span>
  )

  if (editable) {
    return (
      <button
        type="button"
        className={`${classes} hover:opacity-90`}
        aria-label={`${description} — modifier ou supprimer`}
        onClick={() => onPick({ room, date: toIsoDate(day), hour, slot })}
        {...detailHandlers}
      >
        {content}
      </button>
    )
  }

  // Résa d'un autre membre, ou sienne déjà commencée : focusable (donc
  // consultable au clavier) mais non modifiable — `aria-disabled` plutôt que
  // `disabled` pour rester atteignable.
  return (
    <button
      type="button"
      aria-disabled="true"
      className={`${classes} cursor-default`}
      aria-label={description}
      {...detailHandlers}
    >
      {content}
    </button>
  )
}

/**
 * Alternative accessible à la grille (CLAUDE.md §3.5) : l'occupation de chaque
 * jour affiché, salle par salle, en texte intégral.
 */
function DayList({ rooms, days }: { rooms: CalendarRoom[]; days: Date[] }) {
  return (
    <section aria-labelledby="calendar-day-list-heading" className="space-y-4">
      <h3 id="calendar-day-list-heading" className="text-sm font-medium">
        Vue liste
      </h3>
      {days.map((day) => (
        <div key={day.toISOString()} className="space-y-2">
          <h4 className="text-sm font-medium capitalize">{formatDayLabel(day)}</h4>
          <ul className="space-y-2">
            {rooms.map((room) => {
              const busy = slotsOfDay(room.slots, day)
              return (
                <li key={room.id}>
                  <p className="text-sm font-medium">
                    {room.name}
                    {!room.is_bookable && (
                      <span className="font-normal text-neutral-500 dark:text-neutral-400">
                        {' '}
                        — lecture seule
                      </span>
                    )}
                  </p>
                  {busy.length === 0 ? (
                    <p className="text-sm text-neutral-600 dark:text-neutral-300">
                      Aucune réservation ce jour.
                    </p>
                  ) : (
                    <ul className="ml-4 list-disc text-sm text-neutral-700 dark:text-neutral-200">
                      {busy.map((slot) => (
                        <li key={`${slot.starts_at}-${slot.ends_at}`}>{describeSlot(slot)}</li>
                      ))}
                    </ul>
                  )}
                </li>
              )
            })}
          </ul>
        </div>
      ))}
    </section>
  )
}

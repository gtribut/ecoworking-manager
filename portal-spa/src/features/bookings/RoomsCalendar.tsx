import type { CalendarRef, DateSelectInfo, DatesSetInfo, EventClickInfo } from '@fullcalendar/react'
import FullCalendar from '@fullcalendar/react'
import dayGridPlugin from '@fullcalendar/react/daygrid'
import interactionPlugin from '@fullcalendar/react/interaction'
import frLocale from '@fullcalendar/react/locales/fr'
import classicTheme from '@fullcalendar/react/themes/classic'
import timeGridPlugin from '@fullcalendar/react/timegrid'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { QueryError } from '@/components/QueryError'
import { Alert } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { ConfirmButton } from '@/components/ui/confirm-button'
import {
  Popover,
  PopoverAnchor,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
} from '@/components/ui/popover'
import { Spinner } from '@/components/ui/spinner'
import { Toggle } from '@/components/ui/toggle'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import {
  addDays,
  atHour,
  DEFAULT_HOURS,
  EXTERNAL_HOURS,
  FULL_DAY_HOURS,
  formatDayLabel,
  formatOccupant,
  formatPeriodLabel,
  formatTime,
  startOfWeek,
  toIsoDate,
} from './calendar'
import {
  type EventSlotProps,
  EXTERNAL_SELECT_CONSTRAINT,
  isExternalHalfDaySelection,
  mapAvailabilityToEvents,
  type PickedRange,
  rangeFromSelection,
  readEventSlotProps,
  roomTone,
} from './calendarEvents'
import type { CalendarRoom } from './types'
import { useRooms, useRoomsAvailability } from './useBookings'

// Feuilles de style FullCalendar v7 : squelette + thème « classic ». Leurs
// variables `--fc-classic-*` sont remappées sur les jetons du portail en fin de
// `styles.css` (bloc « Agenda des salles »).
import '@fullcalendar/react/skeleton.css'
import '@fullcalendar/react/themes/classic/theme.css'
import '@fullcalendar/react/themes/classic/palette.css'

/** Vues exposées par la barre d'outils (ADR-0013 D3). */
export type AgendaView = 'timeGridDay' | 'timeGridWeek' | 'dayGridMonth'

const VIEW_LABELS: Record<AgendaView, string> = {
  timeGridDay: 'Jour',
  timeGridWeek: 'Semaine',
  dayGridMonth: 'Mois',
}

const PERIOD_OF_VIEW: Record<AgendaView, 'day' | 'week' | 'month'> = {
  timeGridDay: 'day',
  timeGridWeek: 'week',
  dayGridMonth: 'month',
}

/** Créneau choisi dans la grille, transmis à la modale de réservation. */
export interface PickedSlotRange extends PickedRange {
  /** Salle pré-sélectionnée (première salle réservable affichée), modifiable dans la modale. */
  roomId: number | null
}

interface RoomsCalendarProps {
  isExternal: boolean
  /** Glisser sur un créneau libre : ouvre la modale en création. */
  onPickRange: (range: PickedSlotRange) => void
  /** Bouton « Nouvelle réservation » : modale en création, sans créneau imposé. */
  onNewBooking: () => void
  /** « Modifier » sur sa propre réservation. */
  onEditBooking: (picked: EventSlotProps) => void
  /** « Annuler » sur sa propre réservation (confirmation déjà obtenue). */
  onCancelBooking: (bookingId: number) => void
  /** Salle événementielle : lecture seule, invitation à nous écrire (PRD §3.5.4). */
  onPickEventRoom: () => void
  /** Date affichée par l'agenda, pilotée par le mini-mois du panneau droit. */
  date: Date
  onDateChange: (date: Date) => void
}

/** Rectangle du bloc cliqué : le popover s'y ancre (position fixe, hors flux). */
interface PopoverTarget {
  props: EventSlotProps
  rect: { top: number; left: number; width: number; height: number }
}

function isMobileViewport(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
}

/**
 * Agenda des salles (PRD §3.5.2, ADR-0013 D3) : une seule grille FullCalendar
 * pour toutes les salles, blocs colorés par salle posés côte à côte.
 *
 * **Accessibilité (D4)** : la grille n'est pas navigable au clavier ni
 * restituable par un lecteur d'écran, et elle est exclue de l'audit axe. Les
 * équivalents accessibles vivent sur la même page : le bouton « Nouvelle
 * réservation » (saisie manuelle date / heure / salle) et la liste
 * « Mes réservations » (consultation, modification, annulation).
 */
export function RoomsCalendar({
  isExternal,
  onPickRange,
  onNewBooking,
  onEditBooking,
  onCancelBooking,
  onPickEventRoom,
  date,
  onDateChange,
}: RoomsCalendarProps) {
  const calendarRef = useRef<CalendarRef | null>(null)
  const [view, setView] = useState<AgendaView>(() =>
    isMobileViewport() ? 'timeGridDay' : 'timeGridWeek',
  )
  const [showAllHours, setShowAllHours] = useState(false)
  const [hiddenIds, setHiddenIds] = useState<number[]>([])
  const [popover, setPopover] = useState<PopoverTarget | null>(null)
  const [range, setRange] = useState(() => {
    const start = isMobileViewport() ? atHour(new Date(), 0) : startOfWeek(new Date())
    return {
      from: toIsoDate(start),
      to: toIsoDate(isMobileViewport() ? start : addDays(start, 6)),
    }
  })

  const {
    data: catalog,
    isLoading: catalogLoading,
    isError: catalogError,
    refetch: refetchCatalog,
  } = useRooms()

  const catalogIds = useMemo(() => (catalog ?? []).map((room) => room.id), [catalog])
  const visibleIds = useMemo(
    () => catalogIds.filter((id) => !hiddenIds.includes(id)),
    [catalogIds, hiddenIds],
  )

  const availability = useRoomsAvailability(range.from, range.to, visibleIds)
  const rooms = availability.data?.rooms ?? []

  const events = useMemo(
    () => mapAvailabilityToEvents(rooms, catalogIds, visibleIds),
    [rooms, catalogIds, visibleIds],
  )

  const bounds = isExternal ? EXTERNAL_HOURS : showAllHours ? FULL_DAY_HOURS : DEFAULT_HOURS
  const eventRoom = (catalog ?? []).find((room) => !room.is_bookable) ?? null
  const firstBookableVisible =
    (catalog ?? []).find((room) => room.is_bookable && visibleIds.includes(room.id)) ?? null

  const goto = useCallback((action: 'prev' | 'next' | 'today') => {
    setPopover(null)
    const api = calendarRef.current?.getApi()
    api?.[action]()
  }, [])

  // Le mini-mois du panneau droit pilote l'agenda : on ne navigue que si la
  // date choisie sort de la période déjà affichée (sinon boucle avec
  // `datesSet`, qui remonte à son tour la période courante).
  useEffect(() => {
    const iso = toIsoDate(date)
    if (iso < range.from || iso > range.to) {
      calendarRef.current?.getApi().gotoDate(date)
    }
  }, [date, range])

  const changeView = useCallback((next: AgendaView) => {
    setPopover(null)
    setView(next)
    calendarRef.current?.getApi().changeView(next)
  }, [])

  const handleDatesSet = useCallback(
    (info: DatesSetInfo) => {
      const from = toIsoDate(info.start)
      // `end` est exclusive côté FullCalendar, l'API attend une borne incluse.
      const to = toIsoDate(addDays(info.end, -1))
      setRange((current) => (current.from === from && current.to === to ? current : { from, to }))
      // La date sélectionnée reste celle de l'utilisateur tant qu'elle est
      // visible ; une navigation ‹ / › la ramène au début de la nouvelle période.
      if (date < info.start || date >= info.end) {
        onDateChange(info.start)
      }
    },
    [date, onDateChange],
  )

  const handleSelect = useCallback(
    (info: DateSelectInfo) => {
      calendarRef.current?.getApi().unselect()
      onPickRange({
        roomId: firstBookableVisible?.id ?? null,
        ...rangeFromSelection(info.start, info.end),
      })
    },
    [firstBookableVisible, onPickRange],
  )

  const handleEventClick = useCallback(
    (info: EventClickInfo) => {
      const props = readEventSlotProps(info.event.extendedProps as Record<string, unknown>)
      if (props === null) {
        return
      }
      // Salle événementielle : aucun popover, le bandeau « contactez-nous »
      // de la page porte déjà l'information et le lien mailto (PRD §3.5.4).
      if (!props.roomBookable) {
        onPickEventRoom()
        return
      }
      const rect = info.el.getBoundingClientRect()
      setPopover({
        props,
        rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
      })
    },
    [onPickEventRoom],
  )

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

  const periodLabel = formatPeriodLabel(
    PERIOD_OF_VIEW[view],
    new Date(`${range.from}T00:00:00`),
    new Date(`${range.to}T00:00:00`),
  )

  return (
    <section aria-labelledby="rooms-calendar-heading" className="flex min-w-0 flex-col gap-3">
      <h2 id="rooms-calendar-heading" className="sr-only">
        Calendrier des salles
      </h2>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => goto('today')}>
            Aujourd’hui
          </Button>
          <Button
            variant="outline"
            size="icon-sm"
            aria-label="Période précédente"
            onClick={() => goto('prev')}
          >
            <ChevronLeft aria-hidden="true" />
          </Button>
          <Button
            variant="outline"
            size="icon-sm"
            aria-label="Période suivante"
            onClick={() => goto('next')}
          >
            <ChevronRight aria-hidden="true" />
          </Button>
          <p aria-live="polite" className="ml-1 text-base font-semibold first-letter:uppercase">
            {periodLabel}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <ToggleGroup
            type="single"
            value={view}
            aria-label="Affichage du calendrier"
            onValueChange={(next) => next !== '' && changeView(next as AgendaView)}
          >
            {(Object.keys(VIEW_LABELS) as AgendaView[]).map((value) => (
              <ToggleGroupItem key={value} value={value}>
                {VIEW_LABELS[value]}
              </ToggleGroupItem>
            ))}
          </ToggleGroup>

          <Button size="sm" onClick={onNewBooking}>
            <Plus aria-hidden="true" />
            Nouvelle réservation
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <fieldset className="flex flex-wrap items-center gap-2">
          <legend className="sr-only">Salles affichées</legend>
          {catalog.map((room, index) => (
            <RoomChip
              key={room.id}
              room={room}
              tone={roomTone(room, index)}
              visible={visibleIds.includes(room.id)}
              onToggle={() =>
                setHiddenIds((current) =>
                  current.includes(room.id)
                    ? current.filter((id) => id !== room.id)
                    : [...current, room.id],
                )
              }
            />
          ))}
        </fieldset>

        {!isExternal && (
          <Toggle
            size="sm"
            variant="outline"
            pressed={showAllHours}
            onPressedChange={setShowAllHours}
          >
            Voir 24 h
          </Toggle>
        )}

        {eventRoom !== null && (
          <Button variant="ghost" size="sm" onClick={onPickEventRoom}>
            Réserver {eventRoom.name}
          </Button>
        )}

        <p className="ml-auto hidden text-sm text-muted-foreground lg:block">
          Cliquez-glissez sur un créneau libre pour réserver
        </p>
      </div>

      {availability.isError && (
        <QueryError
          message="Impossible de charger le calendrier des salles."
          onRetry={() => void availability.refetch()}
        />
      )}
      {visibleIds.length === 0 && (
        <Alert variant="info">Sélectionnez au moins une salle à afficher.</Alert>
      )}

      {/* Alternative accessible obligatoire (ADR-0013 D4) : annoncée avant la
          grille, qui n'est ni navigable au clavier ni auditée par axe. */}
      <p className="sr-only">
        La grille de l’agenda ci-dessous est un composant visuel : elle n’est pas navigable au
        clavier. Utilisez le bouton « Nouvelle réservation » pour réserver une salle, et la liste «
        Mes prochaines réservations » pour consulter, modifier ou annuler vos réservations.
      </p>

      {availability.isLoading && <Spinner label="Chargement du calendrier…" />}

      {/*
       * FullCalendar v7 n'expose plus de classe racine `.fc` (ses classes sont
       * hachées) : on la pose ici, c'est le sélecteur d'exclusion de l'audit
       * axe documenté dans `e2e/support/fixtures.ts` et le point d'accroche des
       * variables de thème dans `styles.css`.
       */}
      <div className="fc ew-agenda min-w-0">
        <FullCalendar
          ref={calendarRef}
          plugins={[timeGridPlugin, dayGridPlugin, interactionPlugin, classicTheme]}
          initialView={view}
          initialDate={date}
          locale={frLocale}
          firstDay={1}
          headerToolbar={false}
          height="auto"
          expandRows
          allDaySlot={false}
          nowIndicator
          slotDuration="01:00:00"
          snapDuration="00:30:00"
          slotMinTime={`${String(bounds.start).padStart(2, '0')}:00:00`}
          slotMaxTime={`${String(bounds.end).padStart(2, '0')}:00:00`}
          slotEventOverlap={false}
          editable={false}
          selectable
          selectMirror
          select={handleSelect}
          selectAllow={
            isExternal
              ? (span) => isExternalHalfDaySelection(span.start, span.end)
              : (span) => span.end.getTime() > Date.now()
          }
          selectConstraint={isExternal ? EXTERNAL_SELECT_CONSTRAINT : undefined}
          eventClick={handleEventClick}
          events={events}
          datesSet={handleDatesSet}
          dayMaxEvents={3}
        />
      </div>

      <EventPopover
        target={popover}
        onClose={() => setPopover(null)}
        onEdit={onEditBooking}
        onCancel={onCancelBooking}
      />
    </section>
  )
}

/** Chip de filtre d'une salle : pastille de couleur **et** nom (jamais la couleur seule). */
function RoomChip({
  room,
  tone,
  visible,
  onToggle,
}: {
  room: CalendarRoom | { id: number; name: string; type: string; is_bookable: boolean }
  tone: string
  visible: boolean
  onToggle: () => void
}) {
  return (
    <Toggle
      size="sm"
      variant="outline"
      pressed={visible}
      onPressedChange={onToggle}
      className="rounded-full data-[state=off]:border-dashed data-[state=off]:text-muted-foreground"
    >
      <span aria-hidden="true" className={`ew-chip-dot ew-chip-dot--${tone}`} />
      {room.name}
      <span className="sr-only">{visible ? ' — affichée' : ' — masquée'}</span>
    </Toggle>
  )
}

/**
 * Détail d'un bloc cliqué (PRD §3.5.2 / §3.5.5). Sa propre réservation est
 * modifiable et annulable ; celle d'un autre membre affiche occupant, entité et
 * libellé (Q4 : transparence), sans action.
 */
function EventPopover({
  target,
  onClose,
  onEdit,
  onCancel,
}: {
  target: PopoverTarget | null
  onClose: () => void
  onEdit: (picked: EventSlotProps) => void
  onCancel: (bookingId: number) => void
}) {
  if (target === null) {
    return null
  }

  const { props, rect } = target
  const { slot } = props
  const occupant = formatOccupant(slot)
  const editable = slot.is_mine && slot.booking_id !== null && slot.cancellable

  return (
    <Popover open onOpenChange={(next) => !next && onClose()}>
      <PopoverAnchor
        aria-hidden="true"
        className="pointer-events-none fixed"
        style={{ top: rect.top, left: rect.left, width: rect.width, height: rect.height }}
      />
      <PopoverContent
        align="start"
        aria-label={`Réservation ${props.roomName}`}
        onCloseAutoFocus={(event) => event.preventDefault()}
        className="w-[18rem] gap-3 p-4"
      >
        <PopoverHeader>
          <PopoverTitle className="text-base">
            {eventHeading(slot.label, slot.is_mine)}
          </PopoverTitle>
          <PopoverDescription className="first-letter:uppercase">
            {formatDayLabel(new Date(slot.starts_at))} · {formatTime(slot.starts_at)} –{' '}
            {formatTime(slot.ends_at)}
          </PopoverDescription>
        </PopoverHeader>

        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
          <dt className="text-muted-foreground">Salle</dt>
          <dd>
            {props.roomName}
            {props.roomCapacity !== null && ` · ${props.roomCapacity} places`}
          </dd>
          <dt className="text-muted-foreground">Occupant</dt>
          <dd>{slot.is_mine ? 'Vous' : (occupant ?? 'Non communiqué')}</dd>
        </dl>

        {editable && slot.booking_id !== null && (
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              className="flex-1"
              onClick={() => {
                onClose()
                onEdit(props)
              }}
            >
              Modifier
            </Button>
            <ConfirmButton
              variant="outline"
              size="sm"
              className="flex-1 text-destructive"
              confirmMessage="Annuler cette réservation ?"
              confirmLabel="Oui, annuler"
              cancelLabel="Non"
              onConfirm={() => {
                onClose()
                onCancel(slot.booking_id as number)
              }}
            >
              Annuler
            </ConfirmButton>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

function eventHeading(label: string | null, isMine: boolean): string {
  if (label !== null && label !== '') {
    return label
  }
  return isMine ? 'Ma réservation' : 'Réservation'
}

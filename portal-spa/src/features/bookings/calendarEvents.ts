import type { EventInput } from '@fullcalendar/react'
import { AFTERNOON, formatOccupant, MORNING, toIsoDate } from './calendar'
import type { CalendarRoom, CalendarSlot } from './types'

/**
 * Passerelle entre `GET /rooms/availability` et FullCalendar (ADR-0013 D3).
 * Module **pur** : aucune dépendance React, testable sans rendre la grille —
 * la grille elle-même n'est pas auditée (D4), sa logique doit donc l'être ici.
 */

/** Tons de salle conservés de la grille maison (D6) : cycle sur 4 couleurs. */
export const ROOM_TONES = ['sky', 'amber', 'violet', 'teal'] as const

/** `event` : salle événementielle, rendue en hachuré neutre (lecture seule). */
export type RoomTone = (typeof ROOM_TONES)[number] | 'event'

/** Ton d'une salle : sa position dans le catalogue, `event` si non réservable. */
export function roomTone(room: { type: string }, index: number): RoomTone {
  if (room.type === 'event_room') {
    return 'event'
  }
  const safe = ((index % ROOM_TONES.length) + ROOM_TONES.length) % ROOM_TONES.length
  return ROOM_TONES[safe] ?? 'sky'
}

/**
 * Couleurs d'un bloc. FullCalendar v7 pose `color` / `contrastColor` en style
 * **inline** (`--fc-event-color`), qui l'emporterait sur toute classe : on y
 * injecte donc des variables CSS, redéfinies par thème dans `styles.css`.
 */
export function toneColors(tone: RoomTone, isMine: boolean): { color: string; contrast: string } {
  const key = isMine ? 'mine' : tone
  return { color: `var(--ew-ev-${key}-bg)`, contrast: `var(--ew-ev-${key}-fg)` }
}

/** Données d'un bloc, relues par le popover au clic (`extendedProps`). */
export interface EventSlotProps {
  roomId: number
  roomName: string
  roomCapacity: number | null
  roomBookable: boolean
  tone: RoomTone
  slot: CalendarSlot
}

/** Libellé porté par le bloc : libellé de la résa, sinon occupant, sinon « Occupé ». */
export function eventTitle(slot: CalendarSlot): string {
  if (slot.label !== null && slot.label !== '') {
    return slot.label
  }
  if (slot.is_mine) {
    return 'Ma réservation'
  }
  return formatOccupant(slot) ?? 'Occupé'
}

/**
 * Disponibilité multi-salles → blocs FullCalendar, salles masquées exclues.
 * `editable: false` : le déplacement / redimensionnement à la souris n'est pas
 * du MVP (modification via la modale, PRD §3.5.5).
 */
export function mapAvailabilityToEvents(
  rooms: readonly CalendarRoom[],
  catalogIds: readonly number[],
  visibleIds: readonly number[],
): EventInput[] {
  const events: EventInput[] = []

  for (const room of rooms) {
    if (!visibleIds.includes(room.id)) {
      continue
    }
    const tone = roomTone(room, catalogIds.indexOf(room.id))
    const colors = toneColors(tone, false)

    for (const slot of room.slots) {
      const mine = slot.is_mine
      const { color, contrast } = mine ? toneColors(tone, true) : colors
      const props: EventSlotProps = {
        roomId: room.id,
        roomName: room.name,
        roomCapacity: room.capacity,
        roomBookable: room.is_bookable,
        tone,
        slot,
      }

      events.push({
        id: `${room.id}-${slot.starts_at}`,
        title: eventTitle(slot),
        start: slot.starts_at,
        end: slot.ends_at,
        color,
        contrastColor: contrast,
        className: `ew-ev ew-ev--${tone}${mine ? ' ew-ev--mine' : ''}`,
        editable: false,
        extendedProps: props,
      })
    }
  }

  return events
}

/** Relit les `extendedProps` d'un bloc cliqué (FullCalendar les type en `any`). */
export function readEventSlotProps(extendedProps: Record<string, unknown>): EventSlotProps | null {
  const slot = extendedProps.slot as CalendarSlot | undefined
  const roomId = extendedProps.roomId
  if (slot === undefined || typeof roomId !== 'number') {
    return null
  }
  return extendedProps as unknown as EventSlotProps
}

/** « 09:00 » à partir d'un instant local. */
export function toHhMm(date: Date): string {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

/** Créneau choisi dans la grille (glisser) ou pré-rempli par le bouton. */
export interface PickedRange {
  date: string
  startTime: string
  endTime: string
}

export function rangeFromSelection(start: Date, end: Date): PickedRange {
  return { date: toIsoDate(start), startTime: toHhMm(start), endTime: toHhMm(end) }
}

/**
 * Créneau proposé par défaut au bouton « Nouvelle réservation » : prochaine
 * heure pleine, une heure de durée, jamais au-delà de minuit.
 */
export function defaultRange(now: Date = new Date()): PickedRange {
  const start = new Date(now)
  start.setMinutes(0, 0, 0)
  start.setHours(start.getHours() + 1)
  const end = new Date(start)
  end.setHours(end.getHours() + 1)
  // 23:00 → 00:00 le lendemain : on referme la journée sur 23:59 côté formulaire.
  const sameDay = end.getDate() === start.getDate()
  return {
    date: toIsoDate(start),
    startTime: toHhMm(start),
    endTime: sameDay ? toHhMm(end) : '23:59',
  }
}

/**
 * Contrainte de sélection des externals (PRD §3.5.3) : demi-journées 9 h-13 h
 * ou 14 h-18 h, jours ouvrés uniquement. Les jours fériés restent tranchés par
 * le serveur (`DeskAvailabilityService`), le client ne les connaît pas.
 */
export const EXTERNAL_SELECT_CONSTRAINT: EventInput = {
  daysOfWeek: [1, 2, 3, 4, 5],
  startTime: `${String(MORNING.start).padStart(2, '0')}:00`,
  endTime: `${String(AFTERNOON.end).padStart(2, '0')}:00`,
}

/** Heure décimale locale (09:30 → 9.5). */
function decimalHour(date: Date): number {
  return date.getHours() + date.getMinutes() / 60
}

export function isExternalHalfDaySelection(start: Date, end: Date): boolean {
  const weekday = start.getDay()
  if (weekday === 0 || weekday === 6) {
    return false
  }
  // Une sélection à cheval sur deux jours n'est jamais une demi-journée.
  if (toIsoDate(start) !== toIsoDate(new Date(end.getTime() - 1))) {
    return false
  }
  const from = decimalHour(start)
  const to = decimalHour(end)
  return (
    (from >= MORNING.start && to <= MORNING.end) || (from >= AFTERNOON.start && to <= AFTERNOON.end)
  )
}

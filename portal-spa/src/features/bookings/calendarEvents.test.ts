import { describe, expect, it } from 'vitest'
import {
  defaultRange,
  eventTitle,
  isExternalHalfDaySelection,
  mapAvailabilityToEvents,
  rangeFromSelection,
  readEventSlotProps,
  roomTone,
  toneColors,
} from './calendarEvents'
import type { CalendarRoom, CalendarSlot } from './types'

/**
 * La grille FullCalendar n'est ni auditée par axe ni pilotable au clavier
 * (ADR-0013 D4) : sa logique métier vit dans ce module pur, testé ici.
 */

function slot(overrides: Partial<CalendarSlot> = {}): CalendarSlot {
  return {
    booking_id: null,
    is_mine: false,
    cancellable: false,
    starts_at: '2026-06-11T10:00:00+02:00',
    ends_at: '2026-06-11T11:00:00+02:00',
    label: null,
    occupant: null,
    ...overrides,
  }
}

function room(overrides: Partial<CalendarRoom> = {}): CalendarRoom {
  return {
    id: 7,
    name: 'Salle Rhône',
    type: 'meeting_room',
    capacity: 6,
    is_bookable: true,
    slots: [],
    ...overrides,
  }
}

describe('roomTone', () => {
  it('cycle sur la palette des salles et isole la salle événementielle', () => {
    expect(roomTone({ type: 'meeting_room' }, 0)).toBe('sky')
    expect(roomTone({ type: 'meeting_room' }, 1)).toBe('amber')
    expect(roomTone({ type: 'meeting_room' }, 2)).toBe('violet')
    expect(roomTone({ type: 'meeting_room' }, 3)).toBe('teal')
    // Cinquième salle : retour au début de la palette.
    expect(roomTone({ type: 'meeting_room' }, 4)).toBe('sky')
    // Salle absente du catalogue (indexOf → -1) : pas de plantage.
    expect(roomTone({ type: 'meeting_room' }, -1)).toBe('teal')
    expect(roomTone({ type: 'event_room' }, 0)).toBe('event')
  })
})

describe('toneColors', () => {
  it('renvoie des variables CSS (thème clair et sombre pilotés par styles.css)', () => {
    expect(toneColors('sky', false)).toEqual({
      color: 'var(--ew-ev-sky-bg)',
      contrast: 'var(--ew-ev-sky-fg)',
    })
    // Ses propres résas passent au vert brand quelle que soit la salle.
    expect(toneColors('amber', true)).toEqual({
      color: 'var(--ew-ev-mine-bg)',
      contrast: 'var(--ew-ev-mine-fg)',
    })
  })
})

describe('eventTitle', () => {
  it('préfère le libellé, sinon l’occupant, sinon « Occupé »', () => {
    expect(eventTitle(slot({ label: 'Comité produit' }))).toBe('Comité produit')
    expect(
      eventTitle(
        slot({
          occupant: {
            kind: 'member',
            first_name: 'Hugo',
            last_name: 'Discret',
            company_name: 'Atelier Numérique',
          },
        }),
      ),
    ).toBe('Hugo Discret (Atelier Numérique)')
    expect(eventTitle(slot())).toBe('Occupé')
    expect(eventTitle(slot({ is_mine: true }))).toBe('Ma réservation')
  })
})

describe('mapAvailabilityToEvents', () => {
  const meeting = room({ slots: [slot({ label: 'Comité produit' })] })
  const eventRoom = room({
    id: 9,
    name: 'Salle événementielle',
    type: 'event_room',
    capacity: 60,
    is_bookable: false,
    slots: [slot({ starts_at: '2026-06-12T18:00:00+02:00', ends_at: '2026-06-12T20:00:00+02:00' })],
  })

  it('transforme chaque créneau occupé en bloc coloré, non déplaçable', () => {
    const events = mapAvailabilityToEvents([meeting], [7, 9], [7, 9])

    expect(events).toHaveLength(1)
    expect(events[0]).toMatchObject({
      id: '7-2026-06-11T10:00:00+02:00',
      title: 'Comité produit',
      start: '2026-06-11T10:00:00+02:00',
      end: '2026-06-11T11:00:00+02:00',
      color: 'var(--ew-ev-sky-bg)',
      contrastColor: 'var(--ew-ev-sky-fg)',
      className: 'ew-ev ew-ev--sky',
      editable: false,
    })
    expect(events[0]?.extendedProps).toMatchObject({
      roomId: 7,
      roomName: 'Salle Rhône',
      roomCapacity: 6,
      roomBookable: true,
      tone: 'sky',
    })
  })

  it('met ses propres réservations en avant (vert brand + contour)', () => {
    const mine = room({ slots: [slot({ booking_id: 42, is_mine: true, cancellable: true })] })
    const events = mapAvailabilityToEvents([mine], [7], [7])

    expect(events[0]?.className).toBe('ew-ev ew-ev--sky ew-ev--mine')
    expect(events[0]?.color).toBe('var(--ew-ev-mine-bg)')
  })

  it('hachure la salle événementielle et la marque comme non réservable', () => {
    const events = mapAvailabilityToEvents([eventRoom], [7, 9], [7, 9])

    expect(events[0]?.className).toBe('ew-ev ew-ev--event')
    expect(events[0]?.extendedProps).toMatchObject({ roomBookable: false, tone: 'event' })
  })

  it('exclut les salles masquées par les chips de filtre', () => {
    const events = mapAvailabilityToEvents([meeting, eventRoom], [7, 9], [7])

    expect(events).toHaveLength(1)
    expect(events[0]?.extendedProps).toMatchObject({ roomId: 7 })
  })
})

describe('readEventSlotProps', () => {
  it('relit les propriétés d’un bloc, et rejette un bloc étranger', () => {
    const props = { roomId: 7, roomName: 'Salle Rhône', slot: slot() }

    expect(readEventSlotProps(props)).toMatchObject({ roomId: 7 })
    expect(readEventSlotProps({})).toBeNull()
    expect(readEventSlotProps({ roomId: 7 })).toBeNull()
  })
})

describe('rangeFromSelection', () => {
  it('convertit un glisser en date + bornes horaires locales', () => {
    expect(
      rangeFromSelection(
        new Date('2026-06-11T09:30:00+02:00'),
        new Date('2026-06-11T11:00:00+02:00'),
      ),
    ).toEqual({ date: '2026-06-11', startTime: '09:30', endTime: '11:00' })
  })
})

describe('defaultRange', () => {
  it('propose la prochaine heure pleine, une heure de durée', () => {
    expect(defaultRange(new Date('2026-06-11T09:20:00'))).toEqual({
      date: '2026-06-11',
      startTime: '10:00',
      endTime: '11:00',
    })
  })

  it('ne déborde pas sur le lendemain en fin de journée', () => {
    expect(defaultRange(new Date('2026-06-11T23:10:00'))).toEqual({
      date: '2026-06-12',
      startTime: '00:00',
      endTime: '01:00',
    })
    expect(defaultRange(new Date('2026-06-11T22:10:00'))).toEqual({
      date: '2026-06-11',
      startTime: '23:00',
      endTime: '23:59',
    })
  })
})

describe('isExternalHalfDaySelection', () => {
  // 2026-06-11 est un jeudi, 2026-06-13 un samedi.
  it('accepte les demi-journées des jours ouvrés (PRD §3.5.3)', () => {
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T09:00:00'), new Date('2026-06-11T13:00:00')),
    ).toBe(true)
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T14:00:00'), new Date('2026-06-11T18:00:00')),
    ).toBe(true)
    // Sélection partielle à l'intérieur d'une demi-journée : autorisée, la
    // modale la ramène ensuite à la demi-journée complète.
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T10:00:00'), new Date('2026-06-11T11:00:00')),
    ).toBe(true)
  })

  it('refuse la pause déjeuner, le hors-bornes, le week-end et les créneaux à cheval', () => {
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T13:00:00'), new Date('2026-06-11T14:00:00')),
    ).toBe(false)
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T12:00:00'), new Date('2026-06-11T15:00:00')),
    ).toBe(false)
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T08:00:00'), new Date('2026-06-11T13:00:00')),
    ).toBe(false)
    expect(
      isExternalHalfDaySelection(new Date('2026-06-13T09:00:00'), new Date('2026-06-13T13:00:00')),
    ).toBe(false)
    expect(
      isExternalHalfDaySelection(new Date('2026-06-11T23:00:00'), new Date('2026-06-12T01:00:00')),
    ).toBe(false)
  })
})

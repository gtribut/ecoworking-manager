import { describe, expect, it } from 'vitest'
import {
  addDays,
  atHour,
  describeSlot,
  findNearestFreeSlot,
  formatOccupant,
  formatPeriodLabel,
  hourRange,
  slotCovering,
  slotsOfDay,
  startOfWeek,
  toIsoDate,
  weekDays,
} from './calendar'
import type { CalendarSlot } from './types'

function slot(startsAt: string, endsAt: string, overrides: Partial<CalendarSlot> = {}) {
  return {
    booking_id: null,
    is_mine: false,
    cancellable: false,
    starts_at: startsAt,
    ends_at: endsAt,
    label: null,
    occupant: null,
    ...overrides,
  } satisfies CalendarSlot
}

describe('calendar', () => {
  it('ramène n’importe quel jour au lundi de sa semaine', () => {
    // 2026-09-16 = mercredi ; 2026-09-13 = dimanche (semaine précédente).
    expect(toIsoDate(startOfWeek(new Date('2026-09-16T15:00:00')))).toBe('2026-09-14')
    expect(toIsoDate(startOfWeek(new Date('2026-09-13T15:00:00')))).toBe('2026-09-07')
    expect(toIsoDate(startOfWeek(new Date('2026-09-14T00:30:00')))).toBe('2026-09-14')
  })

  it('énumère les 7 jours de la semaine et décale les dates', () => {
    const days = weekDays(startOfWeek(new Date('2026-09-16T09:00:00')))
    expect(days).toHaveLength(7)
    expect(toIsoDate(days[0] as Date)).toBe('2026-09-14')
    expect(toIsoDate(days[6] as Date)).toBe('2026-09-20')
    expect(toIsoDate(addDays(new Date('2026-09-30T09:00:00'), 1))).toBe('2026-10-01')
  })

  it('produit les heures affichées selon les bornes', () => {
    expect(hourRange({ start: 8, end: 20 })).toHaveLength(12)
    expect(hourRange({ start: 9, end: 18 })[0]).toBe(9)
    expect(hourRange({ start: 0, end: 24 })).toHaveLength(24)
  })

  it('détecte le créneau occupé couvrant une heure (bornes semi-ouvertes)', () => {
    const busy = [slot('2026-09-16T10:00:00+02:00', '2026-09-16T12:00:00+02:00')]
    const day = new Date('2026-09-16T00:00:00')

    expect(slotCovering(busy, atHour(day, 10), atHour(day, 11))).not.toBeNull()
    expect(slotCovering(busy, atHour(day, 11), atHour(day, 12))).not.toBeNull()
    // 12:00 est la borne de fin : le créneau suivant est libre.
    expect(slotCovering(busy, atHour(day, 12), atHour(day, 13))).toBeNull()
    expect(slotCovering(busy, atHour(day, 9), atHour(day, 10))).toBeNull()
  })

  it('filtre et ordonne les créneaux d’une journée', () => {
    const busy = [
      slot('2026-09-17T09:00:00+02:00', '2026-09-17T10:00:00+02:00'),
      slot('2026-09-16T15:00:00+02:00', '2026-09-16T16:00:00+02:00'),
      slot('2026-09-16T09:00:00+02:00', '2026-09-16T10:00:00+02:00'),
    ]

    const day = slotsOfDay(busy, new Date('2026-09-16T12:00:00'))

    expect(day).toHaveLength(2)
    expect(day[0]?.starts_at).toBe('2026-09-16T09:00:00+02:00')
  })

  it('propose le créneau libre le plus proche après un conflit', () => {
    const busy = [slot('2026-09-16T10:00:00+02:00', '2026-09-16T12:00:00+02:00')]
    const desired = new Date('2026-09-16T10:00:00+02:00')

    const suggestion = findNearestFreeSlot(
      busy,
      desired,
      60,
      { start: 8, end: 20 },
      new Date('2026-09-16T08:00:00+02:00'),
    )

    // 09:00–10:00 est libre et colle au créneau souhaité (distance 1 h).
    expect(suggestion?.start.getHours()).toBe(9)
    expect(suggestion?.end.getHours()).toBe(10)
  })

  it('ne propose jamais un créneau passé ni hors des bornes affichées', () => {
    const busy: CalendarSlot[] = []
    const desired = new Date('2026-09-16T09:00:00+02:00')

    const suggestion = findNearestFreeSlot(
      busy,
      desired,
      60,
      { start: 8, end: 20 },
      new Date('2026-09-16T17:30:00+02:00'),
    )

    // Première demi-heure disponible à partir de « maintenant », pas 09:00.
    expect(suggestion?.start.getHours()).toBe(17)
    expect(suggestion?.start.getMinutes()).toBe(30)
    expect(suggestion?.end.getHours()).toBe(18)
  })

  it('ne propose rien quand la journée est entièrement occupée', () => {
    const busy = [slot('2026-09-16T00:00:00+02:00', '2026-09-17T00:00:00+02:00')]

    const suggestion = findNearestFreeSlot(
      busy,
      new Date('2026-09-16T10:00:00+02:00'),
      60,
      { start: 8, end: 20 },
      new Date('2026-09-16T07:00:00+02:00'),
    )

    expect(suggestion).toBeNull()
  })

  it('décrit un créneau avec occupant, entité et libellé (Q4)', () => {
    const busy = slot('2026-09-16T10:00:00+02:00', '2026-09-16T11:00:00+02:00', {
      label: 'Comité produit',
      occupant: {
        kind: 'member',
        first_name: 'Hugo',
        last_name: 'Discret',
        company_name: 'Atelier Numérique',
      },
    })

    expect(formatOccupant(busy)).toBe('Hugo Discret (Atelier Numérique)')
    expect(describeSlot(busy)).toContain('Occupé par Hugo Discret (Atelier Numérique)')
    expect(describeSlot(busy)).toContain('Comité produit')
    expect(describeSlot({ ...busy, is_mine: true })).toContain('Ma réservation')
  })

  it('n’affiche aucun occupant quand le serveur n’en communique pas (external)', () => {
    const busy = slot('2026-09-16T10:00:00+02:00', '2026-09-16T11:00:00+02:00')

    expect(formatOccupant(busy)).toBeNull()
    expect(describeSlot(busy)).toContain('Occupé')
    expect(describeSlot(busy)).not.toContain('Occupé par')
  })

  // Décision 14/09 : l'opt-out annuaire ne masque plus le nom sur le calendrier
  // des salles (le serveur envoie toujours l'identité d'un membre). Le plan des
  // bureaux, lui, continue de le respecter — cf. plan-utils.ts.
  it('affiche le nom d’un membre même s’il a coché l’opt-out annuaire', () => {
    const busy = slot('2026-09-16T10:00:00+02:00', '2026-09-16T11:00:00+02:00', {
      occupant: {
        kind: 'member',
        first_name: 'Hugo',
        last_name: 'Discret',
        company_name: 'Atelier Numérique',
      },
    })

    expect(formatOccupant(busy)).toBe('Hugo Discret (Atelier Numérique)')
  })

  it('affiche l’entité seule quand la résa n’a pas de membre (résa admin)', () => {
    const busy = slot('2026-09-16T10:00:00+02:00', '2026-09-16T11:00:00+02:00', {
      occupant: {
        kind: 'entity',
        first_name: null,
        last_name: null,
        company_name: 'Cabinet Rhône',
      },
    })

    expect(formatOccupant(busy)).toBe('Cabinet Rhône')
  })
})

describe('formatPeriodLabel', () => {
  it('nomme la journée affichée en vue jour', () => {
    expect(
      formatPeriodLabel('day', new Date('2026-09-14T00:00:00'), new Date('2026-09-14T00:00:00')),
    ).toBe('lundi 14 septembre 2026')
  })

  it('résume la semaine, en factorisant le mois quand il est commun', () => {
    expect(
      formatPeriodLabel('week', new Date('2026-09-14T00:00:00'), new Date('2026-09-20T00:00:00')),
    ).toBe('14 – 20 septembre 2026')
    expect(
      formatPeriodLabel('week', new Date('2026-09-28T00:00:00'), new Date('2026-10-04T00:00:00')),
    ).toBe('28 septembre – 4 octobre 2026')
  })

  it('nomme le mois réellement affiché, débordements voisins inclus', () => {
    // La grille mois de septembre 2026 commence le 31 août et finit le 4 octobre.
    expect(
      formatPeriodLabel('month', new Date('2026-08-31T00:00:00'), new Date('2026-10-04T00:00:00')),
    ).toBe('septembre 2026')
  })
})

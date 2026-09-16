import type { CalendarSlot } from './types'

/** Tout intervalle daté : un créneau du calendrier ou une plage `busy` brute. */
export interface TimeRange {
  starts_at: string
  ends_at: string
}

/** Bornes horaires affichées par défaut (PRD §3.5.2 : 8 h-20 h, 9 h-18 h external). */
export const DEFAULT_HOURS = { start: 8, end: 20 } as const
export const EXTERNAL_HOURS = { start: 9, end: 18 } as const
export const FULL_DAY_HOURS = { start: 0, end: 24 } as const

/** Demi-journées (PRD §3.5.3) — identiques aux bornes serveur pour l'external. */
export const MORNING = { start: 9, end: 13 } as const
export const AFTERNOON = { start: 14, end: 18 } as const

export type CalendarView = 'week' | 'day'

/** Date du jour au format YYYY-MM-DD (fuseau local, pas d'UTC). */
export function toIsoDate(date: Date): string {
  const offset = date.getTimezoneOffset()
  const iso = new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 10)
  return iso
}

/** Minuit local du jour `YYYY-MM-DD`. */
export function parseIsoDate(value: string): Date {
  return new Date(`${value}T00:00:00`)
}

export function addDays(date: Date, days: number): Date {
  const next = new Date(date)
  next.setDate(next.getDate() + days)
  return next
}

/** Lundi de la semaine contenant `date` (semaine française lundi → dimanche). */
export function startOfWeek(date: Date): Date {
  const monday = new Date(date)
  monday.setHours(0, 0, 0, 0)
  const shift = (monday.getDay() + 6) % 7
  return addDays(monday, -shift)
}

/** Les 7 jours de la semaine commençant au lundi donné. */
export function weekDays(monday: Date): Date[] {
  return Array.from({ length: 7 }, (_, index) => addDays(monday, index))
}

/** Heures affichées (bornes incluses côté début, exclues côté fin). */
export function hourRange(bounds: { start: number; end: number }): number[] {
  return Array.from({ length: bounds.end - bounds.start }, (_, index) => bounds.start + index)
}

/** Instant local d'une heure pleine d'un jour donné. */
export function atHour(day: Date, hour: number): Date {
  const date = new Date(day)
  date.setHours(hour, 0, 0, 0)
  return date
}

/** Premier créneau occupé chevauchant [start, end[ — bornes semi-ouvertes. */
export function slotCovering<T extends TimeRange>(slots: T[], start: Date, end: Date): T | null {
  const from = start.getTime()
  const to = end.getTime()
  return (
    slots.find((slot) => {
      const slotStart = new Date(slot.starts_at).getTime()
      const slotEnd = new Date(slot.ends_at).getTime()
      return slotStart < to && slotEnd > from
    }) ?? null
  )
}

/** Les créneaux occupés d'un jour donné, ordonnés chronologiquement. */
export function slotsOfDay(slots: CalendarSlot[], day: Date): CalendarSlot[] {
  const dayStart = atHour(day, 0).getTime()
  const dayEnd = addDays(atHour(day, 0), 1).getTime()
  return slots
    .filter((slot) => {
      const slotStart = new Date(slot.starts_at).getTime()
      const slotEnd = new Date(slot.ends_at).getTime()
      return slotStart < dayEnd && slotEnd > dayStart
    })
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
}

/**
 * Créneau libre le plus proche du créneau souhaité, dans les bornes horaires du
 * jour (PRD §3.5.3 : suggestion après un conflit 409). Pas de suggestion si la
 * journée est pleine. Pas de créneau dans le passé.
 */
export function findNearestFreeSlot(
  slots: TimeRange[],
  desiredStart: Date,
  durationMinutes: number,
  bounds: { start: number; end: number },
  now: Date = new Date(),
): { start: Date; end: Date } | null {
  const day = atHour(desiredStart, 0)
  const dayStart = atHour(day, bounds.start).getTime()
  const lastStart = atHour(day, bounds.end).getTime() - durationMinutes * 60_000
  const step = 30 * 60_000

  let best: { start: Date; end: Date } | null = null
  let bestDistance = Number.POSITIVE_INFINITY

  for (let time = dayStart; time <= lastStart; time += step) {
    if (time < now.getTime()) {
      continue
    }
    const start = new Date(time)
    const end = new Date(time + durationMinutes * 60_000)
    if (slotCovering(slots, start, end) !== null) {
      continue
    }
    const distance = Math.abs(time - desiredStart.getTime())
    if (distance < bestDistance) {
      best = { start, end }
      bestDistance = distance
    }
  }

  return best
}

/** « lundi 14 septembre » (sans l'année, utilisé en en-tête de colonne). */
export function formatDayLabel(day: Date): string {
  return day.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
}

/** « 14 sept. » — version courte pour les en-têtes de la grille semaine. */
export function formatShortDay(day: Date): string {
  return day.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
}

/**
 * Libellé de la période affichée par l'agenda (barre d'outils C14) :
 * « lundi 14 septembre 2026 », « 14 – 20 septembre 2026 », « septembre 2026 ».
 * `endInclusive` est le dernier jour affiché (pas la borne exclusive de
 * FullCalendar).
 */
export function formatPeriodLabel(
  period: 'day' | 'week' | 'month',
  start: Date,
  endInclusive: Date,
): string {
  if (period === 'day') {
    return start.toLocaleDateString('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }
  if (period === 'month') {
    // La vue mois déborde sur les mois voisins : le mois du milieu de plage
    // est celui réellement affiché.
    const middle = new Date((start.getTime() + endInclusive.getTime()) / 2)
    return middle.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
  }
  const sameMonth =
    start.getMonth() === endInclusive.getMonth() &&
    start.getFullYear() === endInclusive.getFullYear()
  const from = sameMonth
    ? String(start.getDate())
    : start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
  const to = endInclusive.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return `${from} – ${to}`
}

/** « 09:00 ». */
export function formatHour(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}

/** « 09:00 » à partir d'un instant ISO. */
export function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

/**
 * Identité affichée d'un occupant : « Hugo Discret (Atelier Numérique) ».
 * `null` quand le serveur ne communique aucune identité (external, PRD §3.5.9).
 *
 * L'opt-out annuaire ne masque PAS le nom ici (décision 14/09) : il ne porte
 * que sur l'annuaire, pas sur l'occupation des salles. Le serveur envoie donc
 * toujours l'identité d'un membre ; seules les résas d'entité arrivent sans nom.
 */
export function formatOccupant(slot: CalendarSlot): string | null {
  const occupant = slot.occupant
  if (occupant === null) {
    return null
  }
  const name = [occupant.first_name, occupant.last_name].filter(Boolean).join(' ').trim()
  if (name !== '') {
    return occupant.company_name ? `${name} (${occupant.company_name})` : name
  }
  // Sans nom : résa posée au nom d'une entité (kind === 'entity').
  return occupant.company_name ?? 'Réservation Ecoworking'
}

/** Description complète d'un créneau occupé (aria-label + panneau de détail). */
export function describeSlot(slot: CalendarSlot): string {
  const occupant = formatOccupant(slot)
  const who = slot.is_mine
    ? 'Ma réservation'
    : occupant === null
      ? 'Occupé'
      : `Occupé par ${occupant}`
  const label = slot.label ? ` — ${slot.label}` : ''
  return `${formatTime(slot.starts_at)} – ${formatTime(slot.ends_at)} · ${who}${label}`
}

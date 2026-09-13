export type AbsencePeriod = 'morning' | 'afternoon' | 'full_day'
export type RecurrenceType = 'none' | 'weekly'

export interface Absence {
  id: number
  date_start: string
  date_end: string | null
  period: AbsencePeriod
  recurrence_type: RecurrenceType
  recurrence_day_of_week: number | null
  notes: string | null
  /** Modifiable jusqu'à la veille du début (calculé en SQL côté back). */
  can_edit: boolean
  /** Supprimable jusqu'au jour de début inclus. */
  can_delete: boolean
}

/** Bureau attitré du membre (PRD §3.4.6). */
export interface AssignedDesk {
  id: number
  name: string
  floor: number | null
  svg_desk_id: string | null
}

export interface PresencePayload {
  desk: AssignedDesk | null
  present_days: string[]
  absences: Absence[]
}

export interface CreateAbsenceInput {
  date_start: string
  date_end?: string
  period?: AbsencePeriod
  recurrence_type?: RecurrenceType
  recurrence_day_of_week?: number
  notes?: string
}

export type UpdateAbsenceInput = CreateAbsenceInput

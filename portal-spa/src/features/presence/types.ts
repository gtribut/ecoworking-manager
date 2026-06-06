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
}

export interface PresencePayload {
  present_days: string[]
  absences: Absence[]
}

export interface CreateAbsenceInput {
  date_start: string
  date_end?: string
  period?: AbsencePeriod
  recurrence_type?: RecurrenceType
  recurrence_day_of_week?: number
}

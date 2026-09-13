export type TicketType = 'desk_half_day' | 'meeting_room_half_day'
export type TicketStatus = 'available' | 'used' | 'restituted' | 'cancelled'
export type DeskPeriod = 'morning' | 'afternoon' | 'full_day'
export type DeskOccupationStatus = 'present' | 'absent' | 'cancelled'

export interface TicketBalances {
  desk_half_day: number
  meeting_room_half_day: number
}

/** Cible de consommation d'un ticket (traçabilité, PRD §3.5.6/§3.5.7). */
export interface TicketUsage {
  kind: 'booking' | 'desk_occupation'
  resource_name: string | null
  date: string | null
}

export interface Ticket {
  id: number
  type: TicketType
  status: TicketStatus
  /** Date d'achat, ou de crédit manuel par l'admin (geste commercial). */
  credited_at: string | null
  consumed_at: string | null
  /** `null` tant que le ticket n'a pas été consommé. */
  usage: TicketUsage | null
}

export interface TicketsPayload {
  balances: TicketBalances
  tickets: Ticket[]
}

export interface Desk {
  id: number
  name: string
  floor: number | null
  svg_desk_id: string | null
  features: string[]
  capacity: number | null
}

export interface DeskAvailability {
  date: string
  period: DeskPeriod
  /** `false` un jour non ouvré (week-end/férié) : `desks` reste vide, cf. `reason`. */
  available: boolean
  reason: 'non_working_day' | null
  count: number
  desks: Desk[]
}

export interface DeskOccupation {
  id: number
  desk_id: number
  desk_name: string
  desk_floor: number | null
  date: string
  period: DeskPeriod
  status: DeskOccupationStatus
  ticket: { id: number; type: TicketType } | null
  /** Encore annulable (délai, PRD §3.5.9) : calculé côté serveur. */
  cancellable: boolean
}

export interface CreateDeskOccupationInput {
  desk_id: number
  date: string
  period: DeskPeriod
}

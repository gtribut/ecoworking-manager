export type TicketType = 'desk_half_day' | 'meeting_room_half_day'
export type TicketStatus = 'available' | 'used' | 'restituted' | 'cancelled'
export type DeskPeriod = 'morning' | 'afternoon' | 'full_day'

export interface TicketBalances {
  desk_half_day: number
  meeting_room_half_day: number
}

export interface Ticket {
  id: number
  type: TicketType
  status: TicketStatus
  consumed_at: string | null
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
  count: number
  desks: Desk[]
}

export interface DeskOccupation {
  id: number
  desk_id: number
  desk_name: string
  date: string
  period: DeskPeriod
  status: string
}

export interface CreateDeskOccupationInput {
  desk_id: number
  date: string
  period: DeskPeriod
}

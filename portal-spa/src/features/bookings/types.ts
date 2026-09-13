import type { TicketType } from '@/features/tickets/types'

export type SlotPeriod = 'morning' | 'afternoon'

export interface Room {
  id: number
  type: string
  name: string
  description: string | null
  capacity: number | null
  features: string[]
  floor: number | null
  external_half_day_price_ht: string | null
  /** Salle événementielle : visible mais non réservable par un membre (PRD §3.5.4). */
  is_bookable: boolean
}

/** Plage horaire occupée renvoyée par l'API de disponibilité (une seule salle). */
export interface BusySlot {
  starts_at: string
  ends_at: string
}

/** Créneau demi-journée proposé aux externals. */
export interface ExternalSlot {
  period: SlotPeriod
  starts_at: string
  ends_at: string
}

export interface RoomAvailability {
  date: string
  busy: BusySlot[]
  external_slots: ExternalSlot[]
  is_external: boolean
}

/** Identité de l'occupant d'un créneau (Q4 : transparence entre membres). */
export interface Occupant {
  first_name: string | null
  last_name: string | null
  company_name: string | null
}

/** Créneau occupé du calendrier multi-salles. */
export interface CalendarSlot {
  /** Renseigné uniquement pour ses propres réservations (seules modifiables). */
  booking_id: number | null
  is_mine: boolean
  starts_at: string
  ends_at: string
  label: string | null
  occupant: Occupant | null
}

export interface CalendarRoom {
  id: number
  name: string
  type: string
  capacity: number | null
  is_bookable: boolean
  slots: CalendarSlot[]
}

export interface RoomsAvailability {
  from: string
  to: string
  rooms: CalendarRoom[]
}

export type BookingStatus = 'confirmed' | 'cancelled' | 'no_show'

export interface Booking {
  id: number
  resource_id: number
  resource_name: string
  title: string | null
  starts_at: string
  ends_at: string
  status: BookingStatus
  is_paid: boolean
  /** Ticket consommé (traçabilité external, PRD §3.5.7). */
  ticket?: { id: number; type: TicketType } | null
  cancellable: boolean
}

/** Corps de création pour un résident (créneau horaire libre). */
export interface CreateResidentBookingInput {
  resource_id: number
  starts_at: string
  ends_at: string
  title?: string
}

/** Corps de création pour un external (demi-journée payante). */
export interface CreateExternalBookingInput {
  resource_id: number
  date: string
  period: SlotPeriod
  title?: string
}

export type CreateBookingInput = CreateResidentBookingInput | CreateExternalBookingInput

/** Modification d'une réservation : même corps que la création (PRD §3.5.5). */
export interface UpdateBookingInput {
  id: number
  payload: CreateBookingInput
}

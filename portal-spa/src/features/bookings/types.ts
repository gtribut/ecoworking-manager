export type SlotPeriod = 'morning' | 'afternoon'

export interface Room {
  id: number
  type: string
  name: string
  description: string | null
  capacity: number | null
  features: string[]
  floor: number | null
  svg_desk_id: string | null
  external_half_day_price_ht: string | null
}

/** Plage horaire occupée renvoyée par l'API de disponibilité. */
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

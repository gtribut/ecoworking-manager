export type AnnouncementType = 'info' | 'event' | 'alert'

export type RegistrationStatus = 'registered' | 'cancelled' | 'attended' | 'no_show'

export interface Announcement {
  id: number
  type: AnnouncementType
  title: string
  body: string
  published_at: string | null
  event_starts_at: string | null
  event_ends_at: string | null
  location: string | null
  requires_registration: boolean
  max_participants: number | null
  registered_count: number | null
  spots_left: number | null
  my_registration_status: RegistrationStatus | null
  is_registered: boolean
  is_registrable: boolean
}

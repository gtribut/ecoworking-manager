import type { PhotoUrls } from '@/components/Avatar'
import type { BillingEntity } from '@/features/billing/types'

export interface ProfileUser {
  id: number
  first_name: string
  last_name: string
  email: string
  theme: 'light' | 'dark' | null
  notify_email: boolean
  notify_in_app: boolean
}

export interface MemberProfileData {
  id: number
  status: string
  job_title: string | null
  bio: string | null
  interests: string | null
  linkedin_url: string | null
  website_url: string | null
  birth_date: string | null
  /** URLs des rendus 80/200/400, servies par l'API authentifiée. */
  photo: PhotoUrls | null
  show_in_directory: boolean
  newsletter_opt_in: boolean
  arrival_date: string | null
  desk: { id: number; name: string; floor: number | null } | null
}

export interface ProfilePayload {
  user: ProfileUser
  profile: MemberProfileData | null
  company: BillingEntity | null
}

/** Champs éditables par le membre (PATCH partiel, PRD §3.4.2). */
export interface ProfileUpdate {
  theme?: 'light' | 'dark' | null
  notify_email?: boolean
  notify_in_app?: boolean
  job_title?: string | null
  bio?: string | null
  interests?: string | null
  linkedin_url?: string | null
  website_url?: string | null
  birth_date?: string | null
  show_in_directory?: boolean
  newsletter_opt_in?: boolean
}

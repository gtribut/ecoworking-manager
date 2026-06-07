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
  photo_path: string | null
  show_in_directory: boolean
  newsletter_opt_in: boolean
  arrival_date: string | null
  desk: { id: number; name: string; floor: number | null } | null
}

export interface CompanyData {
  id: number
  entity_type: string
  name: string
  legal_name: string | null
  legal_form: string | null
  siret: string | null
  vat_number: string | null
  billing_email: string | null
  address: {
    line1: string | null
    line2: string | null
    postal_code: string | null
    city: string | null
    country: string | null
  }
}

export interface ProfilePayload {
  user: ProfileUser
  profile: MemberProfileData | null
  company: CompanyData | null
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

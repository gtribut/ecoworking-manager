import type { PhotoUrls } from '@/components/Avatar'

/** Entrée de l'annuaire (GET /api/directory) — profils opt-in uniquement. */
export interface DirectoryEntry {
  id: number
  first_name: string
  last_name: string
  photo: PhotoUrls | null
  job_title: string | null
  bio: string | null
  interests: string | null
  linkedin_url: string | null
  website_url: string | null
  company: string | null
  desk?: {
    svg_desk_id: string | null
    name: string
    floor: number | null
  } | null
}

/** Fiche occupant d'un bureau — détails seulement si opt-in annuaire. */
export interface PlanOccupantVisible {
  visible: true
  member_profile_id: number
  first_name: string
  last_name: string
  photo: PhotoUrls | null
  job_title: string | null
  bio: string | null
  interests: string | null
  linkedin_url: string | null
  website_url: string | null
  company: string | null
}

/** Occupant opt-out : présence connue, identité masquée (PRD §3.7.5). */
export interface PlanOccupantHidden {
  visible: false
}

export type PlanOccupant = PlanOccupantVisible | PlanOccupantHidden

export type DeskStatus = 'present' | 'partial' | 'absent' | 'free' | 'out_of_service'

export type DeskAssignment = 'assigned_resident' | 'assigned_staff' | 'unassigned'

/** Demi-journée occupée quand le bureau ne l'est qu'à moitié (statut `partial`). */
export type DeskPeriod = 'morning' | 'afternoon'

/** État d'un bureau sur le plan (GET /api/directory/floor-plan). */
export interface PlanDesk {
  resource_id: number
  svg_desk_id: string | null
  name: string
  floor: number | null
  assignment: DeskAssignment | null
  is_own: boolean
  status: DeskStatus
  /** Renseigné uniquement si `status === 'partial'` (recette R-08). */
  present_period: DeskPeriod | null
  occupant: PlanOccupant | null
}

export interface FloorPlan {
  date: string
  /**
   * Jour ouvré (L-V hors fériés). Informatif seulement : depuis le 2026-09-17
   * les bureaux attitrés sont présents 7 j/7 sauf absence déclarée — ce drapeau
   * ne concerne plus que la réservation des bureaux nomades.
   */
  is_working_day: boolean
  desks: PlanDesk[]
}

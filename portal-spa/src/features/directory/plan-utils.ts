import type { PlanDesk } from './types'

/** Numéro lisible du bureau à partir de l'id SVG (`desk-12` → « 12 »). */
export function deskNumber(desk: PlanDesk): string {
  return desk.svg_desk_id?.replace(/^desk-/, '') ?? desk.name
}

/**
 * Nom affichable de l'occupant (PRD §3.7.3/§3.7.5) : identité seulement si
 * opt-in annuaire ; sinon mention anonyme (staff : « Équipe Ecoworking »).
 */
export function occupantDisplayName(desk: PlanDesk): string | null {
  if (!desk.occupant) return null
  if (desk.occupant.visible) {
    const name = `${desk.occupant.first_name} ${desk.occupant.last_name}`
    return desk.occupant.company ? `${name} (${desk.occupant.company})` : name
  }
  return desk.assignment === 'assigned_staff'
    ? 'Équipe Ecoworking'
    : 'Coworker (souhaite rester discret)'
}

export function statusLabel(desk: PlanDesk): string {
  switch (desk.status) {
    case 'present':
      return desk.occupant ? 'présent(e)' : 'occupé'
    case 'partial':
      return 'présent(e) une demi-journée'
    case 'absent':
      return 'absent(e)'
    case 'free':
      return 'libre'
    case 'out_of_service':
      return 'hors service'
  }
}

/**
 * Description complète d'un bureau — utilisée à la fois comme `aria-label`
 * des blocs SVG et comme texte de l'alternative accessible (liste par étage) :
 * les deux représentations restent toujours synchrones.
 */
export function deskLabel(desk: PlanDesk): string {
  const occupant = occupantDisplayName(desk)
  const detail = occupant ? `${occupant}, ${statusLabel(desk)}` : statusLabel(desk)
  const own = desk.is_own ? ' (votre bureau)' : ''

  return `${desk.name} — ${detail}${own}`
}

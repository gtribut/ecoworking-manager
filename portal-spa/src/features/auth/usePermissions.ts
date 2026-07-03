import { useAuth } from './useAuth'

/**
 * Aide à la lecture des permissions de l'utilisateur courant (cf. CLAUDE.md §3.1).
 * Le serveur reste l'autorité : ces helpers ne servent qu'à adapter l'UI.
 *
 * - `canCreateOwnBooking` : résident/staff/membre additionnel (bureau attitré,
 *   réserve salles sans surcoût et déclare ses absences).
 * - `canCreatePaidBooking` : external (réserve à la demi-journée payante, dispose
 *   de tickets bureaux nomades).
 */
export function usePermissions() {
  const { user } = useAuth()
  const permissions = user?.permissions ?? []

  const has = (permission: string): boolean => permissions.includes(permission)

  return {
    has,
    canCreateOwnBooking: has('create-own-booking'),
    canCreatePaidBooking: has('create-paid-booking'),
    /** Annuaire + plan des étages (C12.5) : resident/additional/staff, jamais external. */
    canViewDirectory: has('view-annuaire'),
    /** Résident = dispose d'un bureau attitré (peut déclarer ses absences). */
    isResident: has('create-own-booking'),
    /** External = pas de bureau attitré, paie ses réservations. */
    isExternal: has('create-paid-booking') && !has('create-own-booking'),
  }
}

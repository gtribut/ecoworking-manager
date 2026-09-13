import { useAuth } from './useAuth'

/**
 * Aide à la lecture des permissions de l'utilisateur courant (cf. CLAUDE.md §3.1).
 * Le serveur reste l'autorité : ces helpers ne servent qu'à adapter l'UI.
 *
 * - `canCreateOwnBooking` : résident/staff/membre additionnel (réserve les salles
 *   sans surcoût) — ne dit RIEN d'un bureau attitré : voir `isResident`.
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
    /** Module administratif (factures + documents d'entité) : billing_contact. */
    canViewBilling: has('view-billing-section'),
    /** Calendrier des salles et réservations : tous les rôles d'usage, jamais un billing pur. */
    canViewBookings: has('view-bookings-calendar'),
    /**
     * Résident = dispose d'un bureau attitré (PRD §2.5), seul à pouvoir déclarer
     * ses absences. Vient du serveur (`has_desk`) : la permission
     * `create-own-booking` ne discrimine pas (un `additional` l'a aussi).
     */
    isResident: user?.has_desk === true,
    /** External = pas de bureau attitré, paie ses réservations. */
    isExternal: has('create-paid-booking') && !has('create-own-booking'),
  }
}

import type { LucideIcon } from 'lucide-react'
import {
  CalendarDays,
  FileText,
  Home,
  Newspaper,
  Receipt,
  Ticket,
  UserCheck,
  Users,
} from 'lucide-react'
import { usePermissions } from '@/features/auth/usePermissions'

export interface NavEntry {
  to: string
  /** Libellé complet : sidebar, Sheet « Plus » et nom accessible partout. */
  label: string
  /**
   * Libellé court des onglets de la bottom nav (maquettes C14 : « Résas »,
   * « Actus »). Le nom accessible reste `label` — les tests et l'e2e ciblent
   * les entrées par rôle + nom complet.
   */
  shortLabel?: string
  icon: LucideIcon
  /** Correspondance exacte de la route (accueil uniquement). */
  end?: boolean
}

export interface NavEntries {
  /** Groupe principal de la sidebar. */
  main: NavEntry[]
  /** Groupe « Administratif » (PRD §3.6.1) : contact facturation uniquement. */
  admin: NavEntry[]
  /** Les deux groupes bout à bout, dans l'ordre d'affichage. */
  all: NavEntry[]
}

/**
 * Navigation filtrée par rôle (PRD §2.5) : un module inaccessible n'est jamais
 * proposé — les routes correspondantes sont gardées par <RequireAccess>.
 *
 * Le filtrage est identique à celui du header d'avant C14 (lot B) ; seuls
 * l'ordre (repris des maquettes C14) et le groupe « Administratif » changent.
 * « Mon profil » n'est plus une entrée de nav : il vit dans le bloc profil du
 * bas de sidebar et dans le Sheet « Plus » en mobile (décision U2, cf. D8 pour
 * l'absence d'entrée « Mon entreprise »).
 */
/**
 * Même règle d'activité que `NavLink` (`end` = correspondance exacte, sinon
 * le préfixe de segment) : la sidebar a besoin de l'état actif en amont du
 * lien, pour le poser sur `SidebarMenuButton`.
 */
export function isEntryActive(entry: NavEntry, pathname: string): boolean {
  if (entry.end === true) return pathname === entry.to
  return pathname === entry.to || pathname.startsWith(`${entry.to}/`)
}

export function useNavEntries(): NavEntries {
  const { isResident, isExternal, canViewDirectory, canViewBilling, canViewBookings } =
    usePermissions()

  const main: NavEntry[] = [
    { to: '/', label: 'Accueil', icon: Home, end: true },
    // Calendrier des salles : jamais pour un contact facturation pur.
    ...(canViewBookings
      ? [{ to: '/bookings', label: 'Réservations', shortLabel: 'Résas', icon: CalendarDays }]
      : []),
    ...(isExternal ? [{ to: '/tickets', label: 'Tickets', icon: Ticket }] : []),
    // Présence/absences : seulement avec un bureau attitré (pas les `additional`).
    ...(isResident ? [{ to: '/presence', label: 'Présence', icon: UserCheck }] : []),
    // C12.5 — Annuaire (masqué aux external : pas de view-annuaire)
    ...(canViewDirectory ? [{ to: '/directory', label: 'Annuaire', icon: Users }] : []),
    // Actualités : lisibles par tous les rôles, y compris un contact facturation
    // pur (il fait partie des audiences) — seule l'INSCRIPTION à un événement
    // demande `register-event`, côté RsvpButton.
    { to: '/announcements', label: 'Actualités', shortLabel: 'Actus', icon: Newspaper },
    { to: '/documents', label: 'Documents', icon: FileText },
  ]

  const admin: NavEntry[] = canViewBilling
    ? [{ to: '/invoices', label: 'Factures', icon: Receipt }]
    : []

  return { main, admin, all: [...main, ...admin] }
}

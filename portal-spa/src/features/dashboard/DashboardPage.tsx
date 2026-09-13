import type { LucideIcon } from 'lucide-react'
import { Armchair, CalendarOff, Mail, UserCircle } from 'lucide-react'
import { Link } from 'react-router'
import { DashboardAnnouncements } from '@/features/announcements/DashboardAnnouncements'
import { useAuth } from '@/features/auth/useAuth'
import { usePermissions } from '@/features/auth/usePermissions'
import { DashboardUpcomingBookings } from '@/features/bookings/DashboardUpcomingBookings'
import { DashboardDocumentsToValidate } from '@/features/documents/DashboardDocumentsToValidate'
import { DashboardInvoices } from '@/features/invoices/DashboardInvoices'
import { usePageTitle } from '@/lib/usePageTitle'
import { cn } from '@/lib/utils'

interface Tile {
  to: string
  label: string
  icon: LucideIcon
  description: string
}

/** PRD §3.3.2 « Nous contacter » : simple mailto au sujet pré-rempli. */
export const CONTACT_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande d’informations'

/**
 * Accueil du portail (PRD §3.3, recette R-05) : vue récapitulative —
 * documents à valider (en tête, masqué si rien), dernières factures (contacts
 * facturation uniquement), prochaines réservations, actualités, accès rapides
 * et bouton « Nous contacter ». Desktop : 2 colonnes (factures + résas /
 * actualités) ; mobile : 1 colonne dans cet ordre (PRD §3.3.3).
 */
export function DashboardPage() {
  usePageTitle('Accueil — Portail Ecoworking')

  const { user } = useAuth()
  const { has, isResident, isExternal, canViewBookings } = usePermissions()
  // Bloc factures conditionné au rôle billing_contact (PRD §3.3.2) — le
  // serveur reste l'autorité (InvoicePolicy), l'UI évite juste un bloc vide.
  const canSeeInvoices = has('view-entity-invoices')
  const hasFirstColumn = canSeeInvoices || canViewBookings

  const tiles: Tile[] = [
    ...(isExternal
      ? [
          {
            to: '/tickets',
            label: 'Bureaux nomades',
            icon: Armchair,
            description: 'Soldes de tickets et réservation',
          } satisfies Tile,
        ]
      : []),
    ...(isResident
      ? [
          {
            to: '/presence',
            label: 'Ma présence',
            icon: CalendarOff,
            description: 'Déclarer mes absences',
          } satisfies Tile,
        ]
      : []),
    {
      to: '/profile',
      label: 'Mon profil',
      icon: UserCircle,
      description: 'Mes informations et préférences',
    },
  ]

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-semibold">Bonjour {user?.first_name}</h1>

      {/* Documents à valider en tête (ordre mobile PRD §3.3.3) ; masqué si tout est à jour. */}
      <DashboardDocumentsToValidate />

      {/* Deux colonnes seulement si la première a du contenu : sans facture ni
          réservation, les actualités occupent toute la largeur plutôt que la
          moitié, à côté d'un vide. */}
      <div className={cn('grid gap-8', hasFirstColumn && 'lg:grid-cols-2')}>
        {hasFirstColumn && (
          <div className="space-y-8">
            {canSeeInvoices && <DashboardInvoices />}
            {/* Réservations : masquées au contact facturation pur (PRD §2.5). */}
            {canViewBookings && <DashboardUpcomingBookings />}
          </div>
        )}
        <div className="space-y-8">
          <DashboardAnnouncements />
        </div>
      </div>

      <section aria-labelledby="dashboard-quick-links-title" className="space-y-3">
        <h2 id="dashboard-quick-links-title" className="text-lg font-semibold">
          Accès rapides
        </h2>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {tiles.map((tile) => (
            <li key={tile.to}>
              <Link
                to={tile.to}
                className="flex items-start gap-3 rounded-lg border border-neutral-200 bg-white p-4 hover:border-brand-500 dark:border-neutral-800 dark:bg-neutral-900"
              >
                <tile.icon className="size-6 text-brand-600" aria-hidden="true" />
                <span>
                  <span className="block font-medium">{tile.label}</span>
                  <span className="block text-sm text-neutral-500 dark:text-neutral-400">
                    {tile.description}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>

      <p>
        <a
          href={CONTACT_MAILTO}
          className="inline-flex items-center gap-2 rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
        >
          <Mail className="size-4" aria-hidden="true" />
          Nous contacter
        </a>
      </p>
    </div>
  )
}

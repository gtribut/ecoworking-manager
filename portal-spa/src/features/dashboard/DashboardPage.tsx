import type { LucideIcon } from 'lucide-react'
import { Armchair, CalendarDays, CalendarOff, FileText, UserCircle } from 'lucide-react'
import { Link } from 'react-router'
import { DashboardAnnouncements } from '@/features/announcements/DashboardAnnouncements'
import { useAuth } from '@/features/auth/useAuth'
import { usePermissions } from '@/features/auth/usePermissions'
import { DashboardDocumentsToValidate } from '@/features/documents/DashboardDocumentsToValidate'
import { usePageTitle } from '@/lib/usePageTitle'

interface Tile {
  to: string
  label: string
  icon: LucideIcon
  description: string
}

/**
 * Accueil minimal du portail (MVP). Le tableau de bord riche (PRD §3.3 :
 * documents à valider, dernières factures, prochaines résa) sera complété quand
 * les endpoints correspondants existeront.
 */
export function DashboardPage() {
  usePageTitle('Accueil — Portail Ecoworking')

  const { user } = useAuth()
  const { isResident, isExternal } = usePermissions()

  const tiles: Tile[] = [
    {
      to: '/bookings',
      label: 'Réserver une salle',
      icon: CalendarDays,
      description: 'Voir les créneaux et réserver',
    },
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
    {
      to: '/invoices',
      label: 'Mes factures',
      icon: FileText,
      description: 'Consulter et télécharger',
    },
  ]

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-semibold">Bonjour {user?.first_name} 👋</h1>

      {/* C12.4 — Bloc « Documents à valider » (PRD §3.3.2/§5.3, non bloquant),
          en tête comme le préconise l'ordre mobile du PRD §3.3.3. */}
      <DashboardDocumentsToValidate />

      <ul className="grid gap-4 sm:grid-cols-2">
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

      {/* Bloc « à la une » (PRD §3.3.2) : 3 dernières actualités visibles. */}
      <DashboardAnnouncements />
    </div>
  )
}

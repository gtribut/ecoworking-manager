import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { DashboardAnnouncements } from '@/features/announcements/DashboardAnnouncements'
import { useAuth } from '@/features/auth/useAuth'
import { usePermissions } from '@/features/auth/usePermissions'
import { DashboardUpcomingBookings } from '@/features/bookings/DashboardUpcomingBookings'
import { DashboardInvoices } from '@/features/invoices/DashboardInvoices'
import { usePageTitle } from '@/lib/usePageTitle'
import { cn } from '@/lib/utils'
import { DashboardKpiGrid } from './DashboardKpiGrid'

/** Coûteux à construire : une seule instance pour toute la vie du module. */
const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

/** Date du jour en toutes lettres, sous le titre d'accueil (maquettes C14). */
function today(): string {
  const label = DATE_FORMAT.format(new Date())
  return label.charAt(0).toUpperCase() + label.slice(1)
}

/**
 * Accueil du portail — dashboard « bento » (PRD §3.3, maquette C14 :
 * `docs/refonte_ui/maquettes/Main.dc.html` / `Mobile.dc.html`) :
 *
 * 1. Rangée de KPI selon le rôle (`DashboardKpiGrid` : document à valider,
 *    prochaine résa, bureau/tickets, dernière facture) ;
 * 2. Deux colonnes « Mes prochaines réservations » / « Actualités » (une
 *    seule colonne si `canViewBookings` est faux — les actualités restent
 *    visibles à tous) ;
 * 3. Bandeau « Mes dernières factures » (`billing_contact` uniquement).
 *
 * Écarts assumés avec l'ancienne version (cf. rapport U4a) : la section
 * « Accès rapides » est retirée (Ma présence/Bureaux nomades sont désormais
 * dans les KPI, Mon profil est déjà dans la sidebar — U2). Le bouton
 * « Nous contacter » local en mobile a été retiré en U5 (review) : le pied
 * de page (`Footer.tsx`, `pb-20` sous la bottom nav) porte déjà un lien
 * « Contact » identique, quel que soit le viewport — doublon supprimé.
 */
export function DashboardPage() {
  usePageTitle('Accueil — Portail Ecoworking')

  const { user } = useAuth()
  const { has, canViewBookings } = usePermissions()
  // Bloc factures conditionné au rôle billing_contact (PRD §3.3.2) — le
  // serveur reste l'autorité (InvoicePolicy), l'UI évite juste un bandeau vide.
  const canSeeInvoices = has('view-entity-invoices')

  return (
    <PageContainer width="wide" className="space-y-8">
      <PageHeader title={user ? `Bonjour ${user.first_name}` : 'Bonjour'} description={today()} />

      <DashboardKpiGrid />

      {/* Deux colonnes seulement si les résas ont un contenu propre : sans
          module réservations (contact facturation pur), les actualités
          occupent toute la largeur plutôt que la moitié, à côté d'un vide. */}
      <div className={cn('grid gap-6', canViewBookings && 'lg:grid-cols-2')}>
        {canViewBookings && <DashboardUpcomingBookings />}
        <DashboardAnnouncements />
      </div>

      {canSeeInvoices && <DashboardInvoices />}
    </PageContainer>
  )
}

import { Link } from 'react-router'
import { usePermissions } from '@/features/auth/usePermissions'
import { useUpcomingBookings } from '@/features/bookings/useBookings'
import { DashboardDocumentsToValidate } from '@/features/documents/DashboardDocumentsToValidate'
import { euros, InvoiceStatusBadge } from '@/features/invoices/status'
import { DEFAULT_INVOICE_FILTERS } from '@/features/invoices/types'
import { useInvoices } from '@/features/invoices/useInvoices'
import { useProfile } from '@/features/profile/useProfile'
import { useTickets } from '@/features/tickets/useTickets'
import { KpiTile } from './KpiTile'

const WEEKDAY_MONTH_FORMAT = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
})

/** « Aujourd'hui » / « Demain » / date courte — pour rester lisible en une ligne de KPI. */
function relativeDay(iso: string): string {
  const target = new Date(iso)
  const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diffDays = Math.round(
    (startOfDay(target).getTime() - startOfDay(new Date()).getTime()) / 86_400_000,
  )
  if (diffDays === 0) return 'Aujourd’hui'
  if (diffDays === 1) return 'Demain'
  return WEEKDAY_MONTH_FORMAT.format(target)
}

function timeOnly(iso: string): string {
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

/**
 * KPI « Prochaine réservation » (PRD §3.3.2, maquette C14). Requête dédiée
 * (`useUpcomingBookings(1)`) : même endpoint que `DashboardUpcomingBookings`
 * (`upcoming=1`), avec une limite différente — donc une entrée de cache TanStack
 * Query distincte (léger surcoût réseau assumé, cf. rapport U4a).
 */
function NextBookingKpi() {
  const { data, isLoading, isError } = useUpcomingBookings(1)
  const booking = data?.[0] ?? null

  if (isError) {
    return <KpiTile label="Prochaine réservation" value="Indisponible" />
  }

  return (
    <KpiTile
      label="Prochaine réservation"
      loading={isLoading}
      value={
        booking
          ? `${relativeDay(booking.starts_at)} · ${timeOnly(booking.starts_at)}`
          : 'Aucune résa à venir'
      }
      sub={
        booking ? (
          <>
            {booking.resource_name}
            {booking.title ? ` · ${booking.title}` : ''}
          </>
        ) : (
          <Link
            to="/bookings"
            className="font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            Réserver une salle
          </Link>
        )
      }
    />
  )
}

/**
 * KPI « Mon bureau » (résident/staff, `has_desk`). Le nom/étage du bureau
 * vient de `useProfile()` (`profile.desk`, déjà chargé par la page Profil) :
 * aucun nouvel appel API. En l'absence de ce champ (edge case), repli sur un
 * libellé générique — voir le point ouvert du rapport U4a (pas de statut
 * « présente aujourd'hui » disponible sans dépendre du module Présence,
 * hors périmètre U4a).
 */
function DeskKpi() {
  const { data, isLoading, isError } = useProfile()
  const desk = data?.profile?.desk ?? null

  if (isError) {
    return <KpiTile label="Mon bureau" value="Indisponible" />
  }

  return (
    <KpiTile
      label="Mon bureau"
      loading={isLoading}
      value={desk ? `Bureau ${desk.name}` : 'Bureau attitré'}
      sub={
        <>
          {desk?.floor !== null && desk?.floor !== undefined ? `Étage ${desk.floor} · ` : ''}
          <Link
            to="/presence"
            className="font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            Ma présence
          </Link>
        </>
      }
    />
  )
}

/** KPI « Tickets restants » (external, PRD §3.5.6) — soldes de `useTickets()`. */
function TicketsKpi() {
  const { data, isLoading, isError } = useTickets()
  const balances = data?.balances ?? null

  if (isError) {
    return <KpiTile label="Tickets restants" value="Indisponible" />
  }

  return (
    <KpiTile
      label="Tickets restants"
      loading={isLoading}
      value={
        balances ? `${balances.desk_half_day} bureau${balances.desk_half_day > 1 ? 'x' : ''}` : '—'
      }
      sub={
        <>
          {balances
            ? `${balances.meeting_room_half_day} salle${balances.meeting_room_half_day > 1 ? 's' : ''} · `
            : ''}
          <Link
            to="/tickets"
            className="font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            Bureaux nomades
          </Link>
        </>
      }
    />
  )
}

/**
 * KPI « Dernière facture » (billing_contact, PRD §3.3.2) — même requête que
 * `DashboardInvoices` (`useInvoices(DEFAULT_INVOICE_FILTERS)`) : clé de cache
 * identique, donc une seule requête réseau partagée entre la tuile et le
 * bandeau plus bas sur la page.
 */
function LastInvoiceKpi() {
  const { data, isLoading, isError } = useInvoices(DEFAULT_INVOICE_FILTERS)
  const invoice = data?.data[0] ?? null

  if (isError) {
    return <KpiTile label="Dernière facture" value="Indisponible" />
  }

  return (
    <KpiTile
      label="Dernière facture"
      loading={isLoading}
      value={invoice ? `${euros.format(Number(invoice.total_ttc))} TTC` : 'Aucune facture'}
      sub={
        invoice ? (
          <>
            {invoice.number ?? '—'} · <InvoiceStatusBadge status={invoice.status} />
          </>
        ) : (
          <Link
            to="/invoices"
            className="font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            Voir mes factures
          </Link>
        )
      }
    />
  )
}

/**
 * Rangée de KPI du dashboard (PRD §3.3, maquette C14 « bento ») : jusqu'à 4
 * tuiles selon le rôle et les données déjà chargées ailleurs dans le portail
 * — aucun nouvel appel API (CLAUDE.md §11, garde-fou U4a). Le document à
 * valider (`DashboardDocumentsToValidate`) est toujours affiché en premier
 * dans le DOM et repasse en tête visuellement sur mobile (`orderFirst`).
 */
export function DashboardKpiGrid() {
  const { canViewBookings, isResident, isExternal, has } = usePermissions()
  const canSeeInvoices = has('view-entity-invoices')

  return (
    <section aria-labelledby="dashboard-kpis-title">
      <h2 id="dashboard-kpis-title" className="sr-only">
        Indicateurs clés
      </h2>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <DashboardDocumentsToValidate />
        {canViewBookings && <NextBookingKpi />}
        {isResident && <DeskKpi />}
        {isExternal && <TicketsKpi />}
        {canSeeInvoices && <LastInvoiceKpi />}
      </div>
    </section>
  )
}

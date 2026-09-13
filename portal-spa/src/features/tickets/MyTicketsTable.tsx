import { Ticket as TicketIcon } from 'lucide-react'
import { EmptyState } from '@/components/EmptyState'
import type { Ticket, TicketStatus, TicketType } from './types'

const TYPE_LABELS: Record<TicketType, string> = {
  desk_half_day: 'Bureau — demi-journée',
  meeting_room_half_day: 'Salle — demi-journée',
}

const STATUS_LABELS: Record<TicketStatus, string> = {
  available: 'Disponible',
  used: 'Utilisé',
  restituted: 'Restitué',
  cancelled: 'Annulé',
}

const STATUS_CLASSES: Record<TicketStatus, string> = {
  available: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  used: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  restituted: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  cancelled: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
}

function formatDate(iso: string | null): string {
  if (iso === null) {
    return '—'
  }
  return new Date(iso).toLocaleDateString('fr-FR', { dateStyle: 'medium' })
}

/**
 * Détail par ticket (PRD §3.5.6) : type, statut, date de crédit et
 * utilisation (ressource + date) si consommé. Tri du plus récent au plus
 * ancien (crédité le).
 */
export function MyTicketsTable({ tickets }: { tickets: Ticket[] }) {
  const sorted = [...tickets].sort((a, b) =>
    (b.credited_at ?? '').localeCompare(a.credited_at ?? ''),
  )

  return (
    <section aria-labelledby="tickets-detail-heading" className="space-y-4">
      <h2 id="tickets-detail-heading" className="text-lg font-medium">
        Détail de mes tickets
      </h2>

      {sorted.length === 0 ? (
        <EmptyState icon={TicketIcon} title="Aucun ticket pour le moment." />
      ) : (
        <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
          <table className="w-full text-left text-sm">
            <caption className="sr-only">
              Détail de mes tickets, du plus récent au plus ancien
            </caption>
            <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
              <tr>
                <th scope="col" className="px-4 py-3 font-medium">
                  Type
                </th>
                <th scope="col" className="px-4 py-3 font-medium">
                  Statut
                </th>
                <th scope="col" className="px-4 py-3 font-medium">
                  Crédité le
                </th>
                <th scope="col" className="px-4 py-3 font-medium">
                  Utilisation
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {sorted.map((ticket) => (
                <tr key={ticket.id}>
                  <th scope="row" className="px-4 py-3 font-medium">
                    {TYPE_LABELS[ticket.type]}
                  </th>
                  <td className="px-4 py-3">
                    <span
                      className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[ticket.status]}`}
                    >
                      {STATUS_LABELS[ticket.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3">{formatDate(ticket.credited_at)}</td>
                  <td className="px-4 py-3">
                    {ticket.usage
                      ? `${ticket.usage.resource_name ?? '—'} · ${formatDate(ticket.usage.date)}`
                      : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

import { CalendarPlus } from 'lucide-react'
import { useState } from 'react'
import { EmptyState } from '@/components/EmptyState'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatBookingRange } from './format'
import type { Booking, BookingStatus } from './types'
import { useBookings } from './useBookings'

const STATUS_LABELS: Record<BookingStatus, string> = {
  confirmed: 'Confirmée',
  cancelled: 'Annulée',
  no_show: 'Absence',
}

const STATUS_CLASSES: Record<BookingStatus, string> = {
  confirmed: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  cancelled: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
  no_show: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
}

const TICKET_LABELS: Record<string, string> = {
  meeting_room_half_day: 'Salle — demi-journée',
  desk_half_day: 'Bureau — demi-journée',
}

type Scope = 'upcoming' | 'past'

interface MyBookingsListProps {
  isExternal: boolean
  onEdit: (booking: Booking) => void
}

/**
 * « Mes prochaines réservations » (chronologique) et onglet « Historique »
 * (PRD §3.5.7). Pour l'external, le ticket consommé est indiqué par résa.
 */
export function MyBookingsList({ isExternal, onEdit }: MyBookingsListProps) {
  const [scope, setScope] = useState<Scope>('upcoming')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, refetch } = useBookings(scope, page)

  function switchScope(next: Scope) {
    setScope(next)
    setPage(1)
  }

  return (
    <section aria-labelledby="my-bookings-heading" className="space-y-4">
      <h2 id="my-bookings-heading" className="text-lg font-medium">
        {scope === 'upcoming' ? 'Mes prochaines réservations' : 'Historique de mes réservations'}
      </h2>

      <div className="flex flex-wrap gap-2">
        <Button
          variant={scope === 'upcoming' ? 'primary' : 'secondary'}
          size="sm"
          aria-pressed={scope === 'upcoming'}
          onClick={() => switchScope('upcoming')}
        >
          À venir
        </Button>
        <Button
          variant={scope === 'past' ? 'primary' : 'secondary'}
          size="sm"
          aria-pressed={scope === 'past'}
          onClick={() => switchScope('past')}
        >
          Historique
        </Button>
      </div>

      {isLoading && <Spinner label="Chargement des réservations…" />}
      {isError && (
        <QueryError
          message="Impossible de charger vos réservations."
          onRetry={() => void refetch()}
        />
      )}

      {data && data.data.length === 0 && (
        <EmptyState
          icon={CalendarPlus}
          title={
            scope === 'upcoming'
              ? 'Aucune réservation à venir.'
              : 'Aucune réservation passée pour le moment.'
          }
          {...(scope === 'upcoming'
            ? { cta: { label: 'Réservez votre première salle', to: '#rooms-calendar-heading' } }
            : {})}
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">
                {scope === 'upcoming'
                  ? 'Mes réservations de salle à venir'
                  : 'Mes réservations de salle passées'}
              </caption>
              <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Salle
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Créneau
                  </th>
                  {isExternal && (
                    <th scope="col" className="px-4 py-3 font-medium">
                      Ticket
                    </th>
                  )}
                  <th scope="col" className="px-4 py-3 font-medium">
                    Statut
                  </th>
                  <th scope="col" className="px-4 py-3 text-right font-medium">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {data.data.map((booking) => (
                  <tr key={booking.id}>
                    <th scope="row" className="px-4 py-3 font-medium">
                      {booking.resource_name}
                      {booking.title && (
                        <span className="block text-xs font-normal text-neutral-500 dark:text-neutral-400">
                          {booking.title}
                        </span>
                      )}
                    </th>
                    <td className="px-4 py-3">
                      {formatBookingRange(booking.starts_at, booking.ends_at)}
                    </td>
                    {isExternal && (
                      <td className="px-4 py-3">
                        {booking.ticket
                          ? `nº ${booking.ticket.id} — ${TICKET_LABELS[booking.ticket.type] ?? booking.ticket.type}`
                          : '—'}
                      </td>
                    )}
                    <td className="px-4 py-3">
                      <span
                        className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[booking.status]}`}
                      >
                        {STATUS_LABELS[booking.status]}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {booking.cancellable ? (
                        <Button variant="secondary" size="sm" onClick={() => onEdit(booking)}>
                          Modifier
                          <span className="sr-only">
                            {' '}
                            ou supprimer la réservation {booking.resource_name} du{' '}
                            {formatBookingRange(booking.starts_at, booking.ends_at)}
                          </span>
                        </Button>
                      ) : (
                        <span className="text-neutral-500 dark:text-neutral-400">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.meta.last_page > 1 && (
            <nav
              className="flex items-center justify-between"
              aria-label="Pagination des réservations"
            >
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((current) => current + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}
    </section>
  )
}

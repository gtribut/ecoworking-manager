import { Link } from 'react-router'
import { Alert } from '@/components/ui/Alert'
import { Spinner } from '@/components/ui/Spinner'
import { formatBookingRange } from './format'
import { useUpcomingBookings } from './useBookings'

/**
 * Bloc « Mes prochaines réservations » du dashboard (PRD §3.3.2, recette
 * R-05) : 3 résas confirmées à venir max — libellé, salle, date + créneau.
 */
export function DashboardUpcomingBookings() {
  const { data: bookings, isLoading, isError } = useUpcomingBookings(3)

  return (
    <section aria-labelledby="dashboard-bookings-title" className="space-y-3">
      <h2 id="dashboard-bookings-title" className="text-lg font-semibold">
        Mes prochaines réservations
      </h2>

      {isLoading && <Spinner label="Chargement des réservations…" />}
      {isError && <Alert variant="error">Impossible de charger vos réservations.</Alert>}

      {bookings && bookings.length === 0 && (
        <p className="text-sm text-neutral-500 dark:text-neutral-400">
          Aucune réservation à venir.
        </p>
      )}

      {bookings && bookings.length > 0 && (
        <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
          {bookings.map((booking) => (
            <li key={booking.id} className="px-4 py-3 text-sm">
              <span className="block font-medium">
                {booking.title ? `${booking.title} — ` : ''}
                {booking.resource_name}
              </span>
              <span className="block text-neutral-500 dark:text-neutral-400">
                {formatBookingRange(booking.starts_at, booking.ends_at)}
              </span>
            </li>
          ))}
        </ul>
      )}

      <p>
        <Link to="/bookings" className="text-sm text-brand-700 underline dark:text-brand-300">
          Module réservations →
        </Link>
      </p>
    </section>
  )
}

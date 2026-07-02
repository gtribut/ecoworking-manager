import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { ConfirmButton } from '@/components/ui/ConfirmButton'
import { Spinner } from '@/components/ui/Spinner'
import { usePermissions } from '@/features/auth/usePermissions'
import { CalendarSubscription } from '@/features/calendar/CalendarSubscription'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { BookingForm } from './BookingForm'
import type { Booking, BookingStatus } from './types'
import { useBookings, useCancelBooking } from './useBookings'

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

function formatRange(startIso: string, endIso: string): string {
  const start = new Date(startIso)
  const end = new Date(endIso)
  const day = start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
  const t = (d: Date) => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${day}, ${t(start)} – ${t(end)}`
}

export function BookingsPage() {
  usePageTitle('Réservations — Portail Ecoworking')

  const { isExternal } = usePermissions()
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useBookings(page)
  const cancelBooking = useCancelBooking()
  const [cancelError, setCancelError] = useState<string | null>(null)

  async function onCancel(booking: Booking) {
    setCancelError(null)
    try {
      await cancelBooking.mutateAsync(booking.id)
    } catch (error) {
      setCancelError(getApiErrorMessage(error, 'Annulation impossible.'))
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-10">
      <h1 className="text-2xl font-semibold">Réservations</h1>

      <BookingForm isExternal={isExternal} onBooked={() => setPage(1)} />

      <section aria-labelledby="my-bookings-heading" className="space-y-4">
        <h2 id="my-bookings-heading" className="text-lg font-medium">
          Mes réservations
        </h2>

        {isLoading && <Spinner label="Chargement des réservations…" />}
        {isError && <Alert variant="error">Impossible de charger vos réservations.</Alert>}
        {cancelError && <Alert variant="error">{cancelError}</Alert>}

        {data && data.data.length === 0 && (
          <Alert variant="info">Aucune réservation pour le moment.</Alert>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
              <table className="w-full text-left text-sm">
                <caption className="sr-only">Liste de mes réservations de salle</caption>
                <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-medium">
                      Salle
                    </th>
                    <th scope="col" className="px-4 py-3 font-medium">
                      Créneau
                    </th>
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
                          <span className="block text-xs font-normal text-neutral-500">
                            {booking.title}
                          </span>
                        )}
                        {booking.is_paid && (
                          <span className="ml-1 text-xs text-neutral-500">(payante)</span>
                        )}
                      </th>
                      <td className="px-4 py-3">
                        {formatRange(booking.starts_at, booking.ends_at)}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[booking.status]}`}
                        >
                          {STATUS_LABELS[booking.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        {booking.cancellable ? (
                          <ConfirmButton
                            variant="danger"
                            size="sm"
                            disabled={cancelBooking.isPending}
                            confirmMessage="Annuler cette réservation ?"
                            confirmLabel="Oui, annuler"
                            cancelLabel="Non"
                            onConfirm={() => void onCancel(booking)}
                          >
                            Annuler
                            <span className="sr-only"> la réservation {booking.resource_name}</span>
                          </ConfirmButton>
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
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
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
                  onClick={() => setPage((p) => p + 1)}
                >
                  Suivant
                </Button>
              </nav>
            )}
          </>
        )}
      </section>

      <CalendarSubscription />
    </div>
  )
}

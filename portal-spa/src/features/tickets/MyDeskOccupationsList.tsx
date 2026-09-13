import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { ConfirmButton } from '@/components/ui/ConfirmButton'
import { Spinner } from '@/components/ui/Spinner'
import { getApiErrorMessage } from '@/lib/errors'
import type { DeskOccupation, DeskOccupationStatus, DeskPeriod } from './types'
import { useCancelDeskOccupation, useDeskOccupations } from './useTickets'

const PERIOD_LABELS: Record<DeskPeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

const STATUS_LABELS: Record<DeskOccupationStatus, string> = {
  present: 'Réservé',
  absent: 'Absent',
  cancelled: 'Annulé',
}

type Scope = 'upcoming' | 'past'

function formatDate(isoDate: string): string {
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString('fr-FR', { dateStyle: 'long' })
}

/**
 * « Mes bureaux réservés » (PRD §3.5.9) : occupations de bureau nomade à venir
 * (défaut) et historique, annulation avec restitution du ticket (délai Q22
 * transposé, cf. DeskOccupationPolicy::delete).
 */
export function MyDeskOccupationsList() {
  const [scope, setScope] = useState<Scope>('upcoming')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useDeskOccupations(scope, page)
  const cancelOccupation = useCancelDeskOccupation()
  const [feedback, setFeedback] = useState<{ type: 'error' | 'success'; message: string } | null>(
    null,
  )

  function switchScope(next: Scope) {
    setScope(next)
    setPage(1)
  }

  async function onCancel(occupation: DeskOccupation) {
    setFeedback(null)
    try {
      await cancelOccupation.mutateAsync(occupation.id)
      setFeedback({
        type: 'success',
        message: `Réservation du bureau « ${occupation.desk_name} » annulée.`,
      })
    } catch (error) {
      setFeedback({ type: 'error', message: getApiErrorMessage(error, 'Annulation impossible.') })
    }
  }

  return (
    <section aria-labelledby="my-desks-heading" className="space-y-4">
      <h2 id="my-desks-heading" className="text-lg font-medium">
        {scope === 'upcoming' ? 'Mes bureaux réservés' : 'Historique de mes bureaux réservés'}
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

      {feedback && (
        <Alert variant={feedback.type === 'success' ? 'success' : 'error'}>
          {feedback.message}
        </Alert>
      )}

      {isLoading && <Spinner label="Chargement de vos bureaux réservés…" />}
      {isError && <Alert variant="error">Impossible de charger vos bureaux réservés.</Alert>}

      {data && data.data.length === 0 && (
        <Alert variant="info">
          {scope === 'upcoming'
            ? 'Aucun bureau réservé à venir.'
            : 'Aucun bureau réservé passé pour le moment.'}
        </Alert>
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">
                {scope === 'upcoming'
                  ? 'Mes bureaux nomades réservés à venir'
                  : 'Historique de mes bureaux nomades réservés'}
              </caption>
              <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Bureau
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Date
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Période
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Ticket
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
                {data.data.map((occupation) => (
                  <tr key={occupation.id}>
                    <th scope="row" className="px-4 py-3 font-medium">
                      {occupation.desk_name}
                      {occupation.desk_floor !== null && (
                        <span className="block text-xs font-normal text-neutral-500 dark:text-neutral-400">
                          Étage {occupation.desk_floor}
                        </span>
                      )}
                    </th>
                    <td className="px-4 py-3">{formatDate(occupation.date)}</td>
                    <td className="px-4 py-3">{PERIOD_LABELS[occupation.period]}</td>
                    <td className="px-4 py-3">
                      {occupation.ticket ? `nº ${occupation.ticket.id}` : '—'}
                    </td>
                    <td className="px-4 py-3">{STATUS_LABELS[occupation.status]}</td>
                    <td className="px-4 py-3 text-right">
                      {occupation.cancellable ? (
                        <ConfirmButton
                          variant="danger"
                          size="sm"
                          disabled={cancelOccupation.isPending}
                          confirmMessage="Annuler cette réservation ?"
                          confirmLabel="Oui, annuler"
                          cancelLabel="Non"
                          onConfirm={() => void onCancel(occupation)}
                        >
                          Annuler
                          <span className="sr-only">
                            {' '}
                            la réservation du bureau {occupation.desk_name} du{' '}
                            {formatDate(occupation.date)}
                          </span>
                        </ConfirmButton>
                      ) : (
                        <span className="text-neutral-500 dark:text-neutral-400">
                          {scope === 'upcoming' ? 'Non annulable (délai dépassé)' : '—'}
                        </span>
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
              aria-label="Pagination des bureaux réservés"
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

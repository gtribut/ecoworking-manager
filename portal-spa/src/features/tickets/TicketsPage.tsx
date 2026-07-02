import { Armchair, Ticket as TicketIcon } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Spinner } from '@/components/ui/Spinner'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import type { Desk, DeskPeriod } from './types'
import { useCreateDeskOccupation, useDeskAvailability, useTickets } from './useTickets'

const PERIOD_LABELS: Record<DeskPeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

function todayIso(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  return new Date(now.getTime() - offset * 60_000).toISOString().slice(0, 10)
}

export function TicketsPage() {
  usePageTitle('Tickets & bureaux nomades — Portail Ecoworking')

  const { data, isLoading, isError } = useTickets()

  return (
    <div className="mx-auto max-w-4xl space-y-10">
      <h1 className="text-2xl font-semibold">Tickets & bureaux nomades</h1>

      {isLoading && <Spinner label="Chargement de vos tickets…" />}
      {isError && <Alert variant="error">Impossible de charger vos tickets.</Alert>}

      {data && (
        <>
          <section aria-labelledby="balances-heading" className="space-y-4">
            <h2 id="balances-heading" className="text-lg font-medium">
              Mes soldes de tickets
            </h2>
            <ul className="grid gap-4 sm:grid-cols-2">
              <li className="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <Armchair className="size-6 text-brand-600" aria-hidden="true" />
                <span>
                  <span className="block text-2xl font-semibold tabular-nums">
                    {data.balances.desk_half_day}
                  </span>
                  <span className="block text-sm text-neutral-500">
                    Demi-journées bureau nomade
                  </span>
                </span>
              </li>
              <li className="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <TicketIcon className="size-6 text-brand-600" aria-hidden="true" />
                <span>
                  <span className="block text-2xl font-semibold tabular-nums">
                    {data.balances.meeting_room_half_day}
                  </span>
                  <span className="block text-sm text-neutral-500">
                    Demi-journées salle de réunion
                  </span>
                </span>
              </li>
            </ul>
          </section>

          <DeskBookingForm deskTicketBalance={data.balances.desk_half_day} />
        </>
      )}
    </div>
  )
}

function DeskBookingForm({ deskTicketBalance }: { deskTicketBalance: number }) {
  const [date, setDate] = useState(todayIso())
  const [period, setPeriod] = useState<DeskPeriod>('full_day')
  const [submitted, setSubmitted] = useState(false)
  const [feedback, setFeedback] = useState<{ type: 'error' | 'success'; message: string } | null>(
    null,
  )

  // Validation client (en plus du Form Request côté back) : pas de date passée.
  const dateError =
    date === ''
      ? 'La date est requise.'
      : date < todayIso()
        ? 'La date ne peut pas être dans le passé.'
        : null

  const availability = useDeskAvailability(date, period, submitted && dateError === null)
  const createOccupation = useCreateDeskOccupation()

  function onSearch(event: React.FormEvent) {
    event.preventDefault()
    setFeedback(null)
    setSubmitted(true)
  }

  async function onBook(desk: Desk) {
    setFeedback(null)
    try {
      await createOccupation.mutateAsync({ desk_id: desk.id, date, period })
      setFeedback({ type: 'success', message: `Bureau « ${desk.name} » réservé.` })
    } catch (error) {
      setFeedback({ type: 'error', message: getApiErrorMessage(error, 'Réservation impossible.') })
    }
  }

  return (
    <section aria-labelledby="desk-booking-heading" className="space-y-4">
      <h2 id="desk-booking-heading" className="text-lg font-medium">
        Réserver un bureau nomade
      </h2>

      {deskTicketBalance === 0 && (
        <Alert variant="info">
          Vous n’avez plus de ticket bureau nomade. Contactez Ecoworking pour en obtenir.
        </Alert>
      )}

      {feedback && (
        <Alert variant={feedback.type === 'success' ? 'success' : 'error'}>
          {feedback.message}
        </Alert>
      )}

      <form onSubmit={onSearch} className="grid gap-4 sm:grid-cols-3 sm:items-end" noValidate>
        <div>
          <Label htmlFor="desk-date">Date</Label>
          <Input
            id="desk-date"
            type="date"
            min={todayIso()}
            value={date}
            aria-invalid={submitted && dateError !== null}
            aria-describedby={submitted && dateError !== null ? 'desk-date-error' : undefined}
            onChange={(event) => {
              setDate(event.target.value)
              setSubmitted(false)
            }}
          />
          {submitted && dateError !== null && (
            <p id="desk-date-error" className="mt-1 text-sm text-red-600">
              {dateError}
            </p>
          )}
        </div>
        <div>
          <Label htmlFor="desk-period">Période</Label>
          <Select
            id="desk-period"
            value={period}
            onChange={(event) => {
              setPeriod(event.target.value as DeskPeriod)
              setSubmitted(false)
            }}
          >
            <option value="full_day">Journée complète</option>
            <option value="morning">Matin</option>
            <option value="afternoon">Après-midi</option>
          </Select>
        </div>
        <Button type="submit">Voir les bureaux disponibles</Button>
      </form>

      {submitted && dateError === null && (
        <div aria-live="polite">
          {availability.isLoading && <Spinner label="Recherche des bureaux disponibles…" />}
          {availability.isError && (
            <Alert variant="error">Impossible de charger les disponibilités.</Alert>
          )}
          {availability.data && availability.data.desks.length === 0 && (
            <Alert variant="info">
              Aucun bureau disponible le {new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR')}{' '}
              ({PERIOD_LABELS[period]}).
            </Alert>
          )}
          {availability.data && availability.data.desks.length > 0 && (
            <div className="rounded-lg border border-neutral-200 dark:border-neutral-800">
              <h3 className="border-b border-neutral-200 px-4 py-2 text-sm font-medium dark:border-neutral-800">
                {availability.data.count} bureau(x) disponible(s) — {PERIOD_LABELS[period]}
              </h3>
              <ul className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {availability.data.desks.map((desk) => (
                  <li key={desk.id} className="flex items-center justify-between px-4 py-2">
                    <span className="text-sm">
                      {desk.name}
                      {desk.floor !== null && (
                        <span className="text-neutral-500"> · étage {desk.floor}</span>
                      )}
                    </span>
                    <Button
                      size="sm"
                      variant="secondary"
                      disabled={createOccupation.isPending || deskTicketBalance === 0}
                      onClick={() => void onBook(desk)}
                    >
                      Réserver<span className="sr-only"> le bureau {desk.name}</span>
                    </Button>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </section>
  )
}

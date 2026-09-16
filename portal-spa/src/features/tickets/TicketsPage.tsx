import { Armchair, Ticket as TicketIcon } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Alert } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Spinner } from '@/components/ui/spinner'
import { usePermissions } from '@/features/auth/usePermissions'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { MyDeskOccupationsList } from './MyDeskOccupationsList'
import { MyTicketsTable } from './MyTicketsTable'
import type { Desk, DeskPeriod } from './types'
import { useCreateDeskOccupation, useDeskAvailability, useTickets } from './useTickets'

const PERIOD_LABELS: Record<DeskPeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

/** PRD §3.5.6/§3.5.9 : pas d'achat en ligne en MVP, on invite à écrire. */
const TICKETS_MAILTO = 'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de tickets'
const DESK_MAILTO = 'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de bureau'

function ContactLink({ mailto, label }: { mailto: string; label: string }) {
  return (
    <a href={mailto} className="ml-1 font-medium underline underline-offset-2 hover:no-underline">
      {label}
    </a>
  )
}

function todayIso(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  return new Date(now.getTime() - offset * 60_000).toISOString().slice(0, 10)
}

/**
 * Week-end (samedi/dimanche) — les bureaux nomades ne sont réservables que
 * les jours ouvrés (règle serveur : DeskAvailabilityService). Les jours
 * fériés ne peuvent pas être calculés côté client : ils sont détectés à
 * l'appel de la disponibilité (`reason: non_working_day`, cf. plus bas).
 */
function isWeekend(isoDate: string): boolean {
  const day = new Date(`${isoDate}T12:00:00`).getDay()
  return day === 0 || day === 6
}

/** Aujourd'hui, ou lundi si on est le week-end (date par défaut du formulaire). */
function nextBookableDateIso(): string {
  let candidate = new Date(`${todayIso()}T12:00:00`)
  while (candidate.getDay() === 0 || candidate.getDay() === 6) {
    candidate = new Date(candidate.getTime() + 86_400_000)
  }
  return candidate.toISOString().slice(0, 10)
}

export function TicketsPage() {
  usePageTitle('Tickets & bureaux nomades — Portail Ecoworking')

  const { isExternal } = usePermissions()
  const { data, isLoading, isError, refetch } = useTickets()

  return (
    <PageContainer width="wide" className="space-y-10">
      <PageHeader title="Tickets & bureaux nomades" />

      {isLoading && <Spinner label="Chargement de vos tickets…" />}
      {isError && (
        <QueryError message="Impossible de charger vos tickets." onRetry={() => void refetch()} />
      )}

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
                  <span className="block text-sm text-neutral-500 dark:text-neutral-400">
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
                  <span className="block text-sm text-neutral-500 dark:text-neutral-400">
                    Demi-journées salle de réunion
                  </span>
                </span>
              </li>
            </ul>

            {/* Le 0-ticket bureau n'est signalé qu'une fois, juste avant le
                formulaire de réservation (DeskBookingForm) — pas ici en plus
                (review lot E pt.8, un seul encart + un seul mailto). */}
            {data.balances.meeting_room_half_day === 0 && (
              <Alert variant="info">
                Vous n’avez plus de ticket salle de réunion : contactez Ecoworking pour en obtenir.
                <ContactLink mailto={TICKETS_MAILTO} label="Nous contacter" />
              </Alert>
            )}
          </section>

          <MyTicketsTable tickets={data.tickets} />

          {isExternal && (
            <>
              <DeskBookingForm deskTicketBalance={data.balances.desk_half_day} />
              <MyDeskOccupationsList />
            </>
          )}
        </>
      )}
    </PageContainer>
  )
}

function DeskBookingForm({ deskTicketBalance }: { deskTicketBalance: number }) {
  const [date, setDate] = useState(nextBookableDateIso())
  const [period, setPeriod] = useState<DeskPeriod>('full_day')
  const [submitted, setSubmitted] = useState(false)

  // Validation client (en plus du Form Request côté back) : pas de date
  // passée, pas de week-end (jours ouvrés uniquement, décision 2026-07-03).
  const dateError =
    date === ''
      ? 'La date est requise.'
      : date < todayIso()
        ? 'La date ne peut pas être dans le passé.'
        : isWeekend(date)
          ? 'Les bureaux nomades ne sont réservables que les jours ouvrés (lundi à vendredi).'
          : null

  const availability = useDeskAvailability(date, period, submitted && dateError === null)
  const createOccupation = useCreateDeskOccupation()

  function onSearch(event: React.FormEvent) {
    event.preventDefault()
    setSubmitted(true)
  }

  async function onBook(desk: Desk) {
    try {
      await createOccupation.mutateAsync({ desk_id: desk.id, date, period })
      toast.success(`Bureau « ${desk.name} » réservé.`)
    } catch (error) {
      toast.error(getApiErrorMessage(error, 'Réservation impossible.'))
    }
  }

  // 0 ticket bureau : on le dit AVANT de proposer le formulaire de recherche
  // (PRD §3.5.9), pas un simple bandeau au-dessus d'un formulaire inerte.
  if (deskTicketBalance === 0) {
    return (
      <section aria-labelledby="desk-booking-heading" className="space-y-4">
        <h2 id="desk-booking-heading" className="text-lg font-medium">
          Réserver un bureau nomade
        </h2>
        <Alert variant="info">
          Vous n’avez plus de ticket bureau nomade. Contactez Ecoworking pour en obtenir.
          <ContactLink mailto={TICKETS_MAILTO} label="Nous contacter" />
        </Alert>
      </section>
    )
  }

  return (
    <section aria-labelledby="desk-booking-heading" className="space-y-4">
      <h2 id="desk-booking-heading" className="text-lg font-medium">
        Réserver un bureau nomade
      </h2>

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
          <NativeSelect
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
          </NativeSelect>
        </div>
        <Button type="submit">Voir les bureaux disponibles</Button>
      </form>

      {submitted && dateError === null && (
        <div aria-live="polite">
          {availability.isLoading && <Spinner label="Recherche des bureaux disponibles…" />}
          {availability.isError && (
            <QueryError
              message="Impossible de charger les disponibilités."
              onRetry={() => void availability.refetch()}
            />
          )}
          {/* Jour non ouvré détecté côté serveur (férié — le week-end est déjà
              bloqué côté client ci-dessus) : message dédié, pas un « 0 dispo ». */}
          {availability.data && !availability.data.available && (
            <Alert variant="info">
              Ce jour n’est pas un jour ouvré (week-end ou jour férié) : aucun bureau nomade n’y est
              réservable.
            </Alert>
          )}
          {availability.data?.available && availability.data.desks.length === 0 && (
            <Alert variant="info">
              Aucun bureau disponible sur ce créneau. Contactez-nous pour étudier les possibilités.
              <ContactLink mailto={DESK_MAILTO} label="Nous contacter pour un bureau" />
            </Alert>
          )}
          {availability.data?.available && availability.data.desks.length > 0 && (
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
                        <span className="text-neutral-500 dark:text-neutral-400">
                          {' '}
                          · étage {desk.floor}
                        </span>
                      )}
                    </span>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={createOccupation.isPending}
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

import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Spinner } from '@/components/ui/Spinner'
import { usePermissions } from '@/features/auth/usePermissions'
import { getApiErrorMessage } from '@/lib/errors'
import type { Absence, AbsencePeriod, CreateAbsenceInput, RecurrenceType } from './types'
import { useCreateAbsence, useDeleteAbsence, usePresence } from './usePresence'

const PERIOD_LABELS: Record<AbsencePeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

const WEEKDAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']

const schema = z
  .object({
    date_start: z.string().min(1, 'La date de début est requise.'),
    date_end: z.string().optional(),
    period: z.enum(['morning', 'afternoon', 'full_day']),
    recurrence_type: z.enum(['none', 'weekly']),
    recurrence_day_of_week: z.string().optional(),
  })
  .refine(
    (values) =>
      values.recurrence_type !== 'weekly' ||
      (values.recurrence_day_of_week !== undefined && values.recurrence_day_of_week !== ''),
    {
      message: 'Choisissez un jour de la semaine pour une absence récurrente.',
      path: ['recurrence_day_of_week'],
    },
  )
  .refine(
    (values) => !values.date_end || values.date_end === '' || values.date_end >= values.date_start,
    { message: 'La date de fin doit suivre la date de début.', path: ['date_end'] },
  )

type FormValues = z.infer<typeof schema>

/** Fenêtre par défaut : du début du mois courant à 3 mois plus tard. */
function defaultRange(): { from: string; to: string } {
  const now = new Date()
  const from = new Date(now.getFullYear(), now.getMonth(), 1)
  const to = new Date(now.getFullYear(), now.getMonth() + 3, 0)
  const iso = (d: Date) => {
    const offset = d.getTimezoneOffset()
    return new Date(d.getTime() - offset * 60_000).toISOString().slice(0, 10)
  }
  return { from: iso(from), to: iso(to) }
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR')
}

export function PresencePage() {
  const { isResident } = usePermissions()

  if (!isResident) {
    return (
      <div className="mx-auto max-w-2xl space-y-6">
        <h1 className="text-2xl font-semibold">Ma présence</h1>
        <Alert variant="info">
          La déclaration de présence est réservée aux résidents disposant d’un bureau attitré. Si
          vous travaillez à la demi-journée, rendez-vous sur la page « Tickets & bureaux nomades ».
        </Alert>
      </div>
    )
  }

  return <PresenceContent />
}

function PresenceContent() {
  const [range] = useState(defaultRange)
  const { data, isLoading, isError } = usePresence(range.from, range.to)
  const createAbsence = useCreateAbsence()
  const deleteAbsence = useDeleteAbsence()
  const [feedback, setFeedback] = useState<{ type: 'error' | 'success'; message: string } | null>(
    null,
  )

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      date_start: '',
      date_end: '',
      period: 'full_day',
      recurrence_type: 'none',
      recurrence_day_of_week: '',
    },
  })

  const recurrenceType = form.watch('recurrence_type') as RecurrenceType

  const onSubmit = form.handleSubmit(async (values) => {
    setFeedback(null)
    const payload: CreateAbsenceInput = {
      date_start: values.date_start,
      period: values.period,
      recurrence_type: values.recurrence_type,
    }
    if (values.recurrence_type === 'weekly') {
      payload.recurrence_day_of_week = Number(values.recurrence_day_of_week)
    } else if (values.date_end && values.date_end !== '') {
      payload.date_end = values.date_end
    }

    try {
      await createAbsence.mutateAsync(payload)
      setFeedback({ type: 'success', message: 'Absence enregistrée.' })
      form.reset()
    } catch (error) {
      setFeedback({
        type: 'error',
        message: getApiErrorMessage(error, 'Enregistrement impossible.'),
      })
    }
  })

  async function onDelete(absence: Absence) {
    setFeedback(null)
    try {
      await deleteAbsence.mutateAsync(absence.id)
    } catch (error) {
      setFeedback({ type: 'error', message: getApiErrorMessage(error, 'Suppression impossible.') })
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-10">
      <h1 className="text-2xl font-semibold">Ma présence</h1>

      {feedback && (
        <Alert variant={feedback.type === 'success' ? 'success' : 'error'}>
          {feedback.message}
        </Alert>
      )}

      <section aria-labelledby="absence-form-heading" className="space-y-4">
        <h2 id="absence-form-heading" className="text-lg font-medium">
          Déclarer une absence
        </h2>
        <p className="text-sm text-neutral-500">
          Signalez vos jours d’absence pour libérer votre bureau aux membres nomades.
        </p>

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="date_start">Date de début</Label>
              <Input
                id="date_start"
                type="date"
                aria-invalid={Boolean(form.formState.errors.date_start)}
                {...form.register('date_start')}
              />
              {form.formState.errors.date_start && (
                <p className="mt-1 text-sm text-red-600">
                  {form.formState.errors.date_start.message}
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="date_end">Date de fin (optionnel)</Label>
              <Input
                id="date_end"
                type="date"
                disabled={recurrenceType === 'weekly'}
                aria-invalid={Boolean(form.formState.errors.date_end)}
                {...form.register('date_end')}
              />
              {form.formState.errors.date_end && (
                <p className="mt-1 text-sm text-red-600">
                  {form.formState.errors.date_end.message}
                </p>
              )}
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="period">Période</Label>
              <Select id="period" {...form.register('period')}>
                <option value="full_day">Journée complète</option>
                <option value="morning">Matin</option>
                <option value="afternoon">Après-midi</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="recurrence_type">Récurrence</Label>
              <Select id="recurrence_type" {...form.register('recurrence_type')}>
                <option value="none">Ponctuelle</option>
                <option value="weekly">Hebdomadaire</option>
              </Select>
            </div>
          </div>

          {recurrenceType === 'weekly' && (
            <div>
              <Label htmlFor="recurrence_day_of_week">Jour de la semaine</Label>
              <Select
                id="recurrence_day_of_week"
                aria-invalid={Boolean(form.formState.errors.recurrence_day_of_week)}
                {...form.register('recurrence_day_of_week')}
              >
                <option value="">Choisir un jour…</option>
                {WEEKDAYS.map((day, index) => (
                  <option key={day} value={index}>
                    {day}
                  </option>
                ))}
              </Select>
              {form.formState.errors.recurrence_day_of_week && (
                <p className="mt-1 text-sm text-red-600">
                  {form.formState.errors.recurrence_day_of_week.message}
                </p>
              )}
            </div>
          )}

          <Button type="submit" disabled={form.formState.isSubmitting || createAbsence.isPending}>
            Enregistrer l’absence
          </Button>
        </form>
      </section>

      <section aria-labelledby="absence-list-heading" className="space-y-4">
        <h2 id="absence-list-heading" className="text-lg font-medium">
          Mes absences déclarées
        </h2>

        {isLoading && <Spinner label="Chargement de vos absences…" />}
        {isError && <Alert variant="error">Impossible de charger vos absences.</Alert>}

        {data && data.absences.length === 0 && (
          <Alert variant="info">Aucune absence déclarée.</Alert>
        )}

        {data && data.absences.length > 0 && (
          <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
            {data.absences.map((absence) => (
              <li key={absence.id} className="flex items-center justify-between gap-4 px-4 py-3">
                <span className="text-sm">
                  {absence.recurrence_type === 'weekly' &&
                  absence.recurrence_day_of_week !== null ? (
                    <span className="block font-medium">
                      Chaque {WEEKDAYS[absence.recurrence_day_of_week]?.toLowerCase()}
                    </span>
                  ) : (
                    <span className="block font-medium">
                      {formatDate(absence.date_start)}
                      {absence.date_end && absence.date_end !== absence.date_start && (
                        <> → {formatDate(absence.date_end)}</>
                      )}
                    </span>
                  )}
                  <span className="text-neutral-500">{PERIOD_LABELS[absence.period]}</span>
                  {absence.notes && (
                    <span className="block text-xs text-neutral-500">{absence.notes}</span>
                  )}
                </span>
                <Button
                  variant="danger"
                  size="sm"
                  disabled={deleteAbsence.isPending}
                  onClick={() => void onDelete(absence)}
                >
                  Supprimer
                  <span className="sr-only"> l’absence du {formatDate(absence.date_start)}</span>
                </Button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

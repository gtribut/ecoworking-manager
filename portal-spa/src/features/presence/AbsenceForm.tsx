import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useRef } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Textarea } from '@/components/ui/Textarea'
import { getApiFieldErrors } from '@/lib/errors'
import type { Absence, CreateAbsenceInput, RecurrenceType } from './types'

/** Libellés indexés par la valeur back (0 = dimanche … 6 = samedi). */
export const WEEKDAYS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']

/** Ordre d'affichage français (lundi d'abord) — les valeurs 0-6 restent celles du back. */
const WEEKDAY_DISPLAY_ORDER = [1, 2, 3, 4, 5, 6, 0]

const schema = z
  .object({
    date_start: z.string().min(1, 'La date de début est requise.'),
    date_end: z.string().optional(),
    period: z.enum(['morning', 'afternoon', 'full_day']),
    recurrence_type: z.enum(['none', 'weekly']),
    recurrence_day_of_week: z.string().optional(),
    notes: z.string().max(255, 'La note ne peut pas dépasser 255 caractères.').optional(),
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

interface AbsenceFormProps {
  /** Absence en cours de modification, ou `null` pour une déclaration. */
  absence: Absence | null
  submitting: boolean
  onSubmit: (payload: CreateAbsenceInput) => Promise<unknown>
  onCancel: () => void
}

function defaultValues(absence: Absence | null): FormValues {
  return {
    date_start: absence?.date_start ?? '',
    date_end: absence?.date_end ?? '',
    period: absence?.period ?? 'full_day',
    recurrence_type: absence?.recurrence_type ?? 'none',
    recurrence_day_of_week:
      absence?.recurrence_day_of_week === null || absence?.recurrence_day_of_week === undefined
        ? ''
        : String(absence.recurrence_day_of_week),
    notes: absence?.notes ?? '',
  }
}

/**
 * Déclaration / modification d'une absence (PRD §3.4.6) : jour unique, plage,
 * ou récurrence hebdomadaire BORNABLE (début + fin + jour, la fin restant
 * facultative). Les erreurs 422 du back sont rattachées à leur champ
 * (`aria-describedby`), doublées d'un message global par la page appelante.
 */
export function AbsenceForm({ absence, submitting, onSubmit, onCancel }: AbsenceFormProps) {
  const firstFieldRef = useRef<HTMLInputElement | null>(null)
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaultValues(absence),
  })
  const { errors } = form.formState
  const recurrenceType = form.watch('recurrence_type') as RecurrenceType

  const { reset } = form
  // Ouverture du formulaire (ou bascule déclaration ↔ modification) : on
  // repart des valeurs de l'absence ciblée et le focus entre dans le
  // formulaire, sans quoi le clavier resterait sur le bouton déclencheur.
  useEffect(() => {
    reset(defaultValues(absence))
    firstFieldRef.current?.focus()
  }, [absence, reset])

  const submit = form.handleSubmit(async (values) => {
    const payload: CreateAbsenceInput = {
      date_start: values.date_start,
      period: values.period,
      recurrence_type: values.recurrence_type,
      ...(values.notes !== undefined && values.notes !== '' ? { notes: values.notes } : {}),
      // La fin borne aussi bien une plage qu'une récurrence hebdomadaire.
      ...(values.date_end !== undefined && values.date_end !== ''
        ? { date_end: values.date_end }
        : {}),
      ...(values.recurrence_type === 'weekly'
        ? { recurrence_day_of_week: Number(values.recurrence_day_of_week) }
        : {}),
    }

    try {
      await onSubmit(payload)
      form.reset(defaultValues(null))
    } catch (error) {
      for (const [field, message] of Object.entries(getApiFieldErrors(error))) {
        if (
          field === 'date_start' ||
          field === 'date_end' ||
          field === 'period' ||
          field === 'recurrence_type' ||
          field === 'recurrence_day_of_week' ||
          field === 'notes'
        ) {
          form.setError(field, { message })
        } else {
          form.setError('date_start', { message })
        }
      }
    }
  })

  const { ref: dateStartRef, ...dateStartField } = form.register('date_start')

  return (
    <form
      onSubmit={(event) => {
        void submit(event)
      }}
      className="space-y-4"
      noValidate
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="date_start">Date de début</Label>
          <Input
            id="date_start"
            type="date"
            aria-invalid={errors.date_start !== undefined}
            aria-describedby={errors.date_start !== undefined ? 'date_start-error' : undefined}
            {...dateStartField}
            ref={(element) => {
              dateStartRef(element)
              firstFieldRef.current = element
            }}
          />
          {errors.date_start && (
            <p id="date_start-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
              {errors.date_start.message}
            </p>
          )}
        </div>
        <div>
          <Label htmlFor="date_end">Date de fin (optionnel)</Label>
          <Input
            id="date_end"
            type="date"
            aria-invalid={errors.date_end !== undefined}
            aria-describedby={errors.date_end !== undefined ? 'date_end-error' : 'date_end-helper'}
            {...form.register('date_end')}
          />
          {errors.date_end ? (
            <p id="date_end-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
              {errors.date_end.message}
            </p>
          ) : (
            <p id="date_end-helper" className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
              {recurrenceType === 'weekly'
                ? 'Dernier jour de la récurrence. Vide = sans fin.'
                : 'Vide = absence d’un seul jour.'}
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
            aria-invalid={errors.recurrence_day_of_week !== undefined}
            aria-describedby={
              errors.recurrence_day_of_week !== undefined
                ? 'recurrence_day_of_week-error'
                : undefined
            }
            {...form.register('recurrence_day_of_week')}
          >
            <option value="">Choisir un jour…</option>
            {WEEKDAY_DISPLAY_ORDER.map((value) => (
              <option key={value} value={value}>
                {WEEKDAYS[value]}
              </option>
            ))}
          </Select>
          {errors.recurrence_day_of_week && (
            <p
              id="recurrence_day_of_week-error"
              className="mt-1 text-sm text-red-700 dark:text-red-300"
            >
              {errors.recurrence_day_of_week.message}
            </p>
          )}
        </div>
      )}

      <div>
        <Label htmlFor="notes">Note (visible par Ecoworking uniquement)</Label>
        <Textarea
          id="notes"
          maxLength={255}
          placeholder="Déplacement client…"
          aria-invalid={errors.notes !== undefined}
          aria-describedby={errors.notes !== undefined ? 'notes-error' : 'notes-helper'}
          {...form.register('notes')}
        />
        {errors.notes ? (
          <p id="notes-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
            {errors.notes.message}
          </p>
        ) : (
          <p id="notes-helper" className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Facultatif. Lue par l’équipe Ecoworking, jamais par les autres membres.
          </p>
        )}
      </div>

      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={submitting}>
          {absence === null ? 'Enregistrer l’absence' : 'Enregistrer les modifications'}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  )
}

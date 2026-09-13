import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { ConfirmButton } from '@/components/ui/ConfirmButton'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Modal } from '@/components/ui/Modal'
import { getApiErrorMessage, getApiFieldErrors, getApiStatus } from '@/lib/errors'
import { fetchRoomAvailability } from './api'
import {
  AFTERNOON,
  DEFAULT_HOURS,
  findNearestFreeSlot,
  formatHour,
  MORNING,
  toIsoDate,
} from './calendar'
import type { CreateBookingInput, SlotPeriod } from './types'
import { useCancelBooking, useCreateBooking, useUpdateBooking } from './useBookings'

/** Créneau à réserver (clic sur une case libre) ou réservation à modifier. */
export type DialogTarget =
  | {
      mode: 'create'
      roomId: number
      roomName: string
      date: string
      startHour: number
    }
  | {
      mode: 'edit'
      bookingId: number
      roomId: number
      roomName: string
      startsAt: string
      endsAt: string
      title: string | null
      /** Créneau encore modifiable (délai Q22) : sinon la modale est en lecture seule. */
      cancellable: boolean
    }

const SLOT_KINDS = ['full_day', 'morning', 'afternoon', 'custom'] as const
type SlotKind = (typeof SLOT_KINDS)[number]

const KIND_LABELS: Record<SlotKind, string> = {
  full_day: 'Journée (9 h – 18 h)',
  morning: 'Matin (9 h – 13 h)',
  afternoon: 'Après-midi (14 h – 18 h)',
  custom: 'Créneau personnalisé',
}

const schema = z
  .object({
    date: z.string().min(1, 'La date est requise.'),
    kind: z.enum(SLOT_KINDS),
    start_time: z.string(),
    end_time: z.string(),
    title: z.string().max(255, 'Le libellé est limité à 255 caractères.'),
  })
  .superRefine((values, context) => {
    if (values.kind !== 'custom') {
      return
    }
    if (values.start_time === '') {
      context.addIssue({
        code: 'custom',
        message: 'L’heure de début est requise.',
        path: ['start_time'],
      })
    }
    if (values.end_time === '') {
      context.addIssue({
        code: 'custom',
        message: 'L’heure de fin est requise.',
        path: ['end_time'],
      })
    }
    if (
      values.start_time !== '' &&
      values.end_time !== '' &&
      values.end_time <= values.start_time
    ) {
      context.addIssue({
        code: 'custom',
        message: 'L’heure de fin doit suivre l’heure de début.',
        path: ['end_time'],
      })
    }
  })

type FormValues = z.infer<typeof schema>

/** Bornes « HH:MM » d'une demi-journée / journée type. */
function timesForKind(kind: SlotKind, values: FormValues): { start: string; end: string } {
  switch (kind) {
    case 'full_day':
      return { start: formatHour(MORNING.start), end: formatHour(AFTERNOON.end) }
    case 'morning':
      return { start: formatHour(MORNING.start), end: formatHour(MORNING.end) }
    case 'afternoon':
      return { start: formatHour(AFTERNOON.start), end: formatHour(AFTERNOON.end) }
    default:
      return { start: values.start_time, end: values.end_time }
  }
}

function toIsoInstant(date: string, time: string): string {
  return new Date(`${date}T${time}:00`).toISOString()
}

function hhmm(iso: string): string {
  const date = new Date(iso)
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function defaultValues(target: DialogTarget, isExternal: boolean): FormValues {
  if (target.mode === 'create') {
    const start = formatHour(target.startHour)
    const end = formatHour(Math.min(target.startHour + 1, 24))
    return {
      date: target.date,
      kind: isExternal ? (target.startHour < MORNING.end ? 'morning' : 'afternoon') : 'custom',
      start_time: start,
      end_time: end,
      title: '',
    }
  }

  const start = hhmm(target.startsAt)
  return {
    date: toIsoDate(new Date(target.startsAt)),
    kind: isExternal ? (start < formatHour(AFTERNOON.start) ? 'morning' : 'afternoon') : 'custom',
    start_time: start,
    end_time: hhmm(target.endsAt),
    title: target.title ?? '',
  }
}

interface BookingDialogProps {
  target: DialogTarget | null
  isExternal: boolean
  onClose: () => void
  onSuccess: (message: string) => void
}

/**
 * Modale de réservation / modification (PRD §3.5.3 et §3.5.5) : ressource et
 * créneau pré-remplis, choix journée / demi-journée / créneau personnalisé
 * (demi-journées seules pour l'external), libellé optionnel, suppression avec
 * confirmation. Validation Zod côté client, doublée du Form Request côté back.
 */
export function BookingDialog({ target, isExternal, onClose, onSuccess }: BookingDialogProps) {
  const createBooking = useCreateBooking()
  const updateBooking = useUpdateBooking()
  const cancelBooking = useCancelBooking()
  const [formError, setFormError] = useState<string | null>(null)
  const [suggestion, setSuggestion] = useState<{ start: Date; end: Date } | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    // Remonte le formulaire à chaque cible : la modale est démontée à la
    // fermeture, `values` garde les champs alignés sur le créneau cliqué.
    values: target === null ? undefined : defaultValues(target, isExternal),
  })

  if (target === null) {
    return null
  }

  const kind = watch('kind')
  const title =
    target.mode === 'create' ? `Réserver ${target.roomName}` : `Ma réservation — ${target.roomName}`
  const kinds: SlotKind[] = isExternal ? ['morning', 'afternoon'] : [...SLOT_KINDS]

  // Filet : si la résa a commencé entre l'affichage de la liste et le clic, le
  // serveur refuserait (403). On le dit au lieu de proposer un formulaire mort.
  if (target.mode === 'edit' && !target.cancellable) {
    return (
      <Modal open title={title} onClose={onClose}>
        <div className="space-y-4">
          <Alert variant="info">
            Cette réservation a déjà commencé : elle n’est plus modifiable ni annulable depuis le
            portail. Contactez Ecoworking pour un cas particulier.
          </Alert>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            <dt className="font-medium">Salle</dt>
            <dd>{target.roomName}</dd>
            <dt className="font-medium">Créneau</dt>
            <dd>
              {new Date(target.startsAt).toLocaleString('fr-FR', {
                dateStyle: 'long',
                timeStyle: 'short',
              })}{' '}
              – {hhmm(target.endsAt)}
            </dd>
            <dt className="font-medium">Libellé</dt>
            <dd>{target.title ?? '—'}</dd>
          </dl>
        </div>
      </Modal>
    )
  }

  async function onSubmit(values: FormValues) {
    if (target === null) {
      return
    }
    setFormError(null)
    setSuggestion(null)

    const times = timesForKind(values.kind, values)
    const payload: CreateBookingInput = isExternal
      ? {
          resource_id: target.roomId,
          date: values.date,
          period:
            values.kind === 'afternoon' ? ('afternoon' as SlotPeriod) : ('morning' as SlotPeriod),
          ...(values.title === '' ? {} : { title: values.title }),
        }
      : {
          resource_id: target.roomId,
          starts_at: toIsoInstant(values.date, times.start),
          ends_at: toIsoInstant(values.date, times.end),
          ...(values.title === '' ? {} : { title: values.title }),
        }

    try {
      if (target.mode === 'create') {
        await createBooking.mutateAsync(payload)
        onSuccess('Réservation confirmée.')
      } else {
        await updateBooking.mutateAsync({ id: target.bookingId, payload })
        onSuccess('Réservation modifiée.')
      }
      onClose()
    } catch (error) {
      // Les champs horaires ne sont rendus qu'en « créneau personnalisé » : hors
      // de ce mode (external, journée, demi-journée) l'erreur serait invisible,
      // on la rattache alors au champ Date, toujours affiché.
      const timesVisible = !isExternal && values.kind === 'custom'
      const fieldErrors = getApiFieldErrors(error)
      for (const [field, message] of Object.entries(fieldErrors)) {
        if (field === 'title') {
          setError('title', { message })
        } else if (field === 'ends_at' && timesVisible) {
          setError('end_time', { message })
        } else if (timesVisible && (field === 'starts_at' || field === 'date')) {
          setError('start_time', { message })
        } else {
          setError('date', { message })
        }
      }
      setFormError(getApiErrorMessage(error, 'Réservation impossible.'))

      if (getApiStatus(error) === 409 && !isExternal) {
        // La suggestion se calcule sur une dispo FRAÎCHE : celle affichée datait
        // d'avant la réservation concurrente qui vient de provoquer le conflit.
        const start = new Date(`${values.date}T${times.start}:00`)
        const end = new Date(`${values.date}T${times.end}:00`)
        const duration = Math.round((end.getTime() - start.getTime()) / 60_000)
        try {
          const fresh = await fetchRoomAvailability(target.roomId, values.date)
          setSuggestion(findNearestFreeSlot(fresh.busy, start, duration, DEFAULT_HOURS))
        } catch {
          setSuggestion(null) // message de conflit seul
        }
      }
    }
  }

  async function onDelete() {
    if (target === null || target.mode !== 'edit') {
      return
    }
    setFormError(null)
    try {
      await cancelBooking.mutateAsync(target.bookingId)
      onSuccess('Réservation annulée.')
      onClose()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Annulation impossible.'))
    }
  }

  return (
    <Modal open title={title} onClose={onClose}>
      <form
        className="space-y-4"
        onSubmit={(event) => {
          void handleSubmit(onSubmit)(event)
        }}
      >
        {formError !== null && (
          <Alert variant="error">
            {formError}
            {suggestion !== null && (
              <span className="mt-2 block">
                Créneau libre le plus proche :{' '}
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => {
                    setValue('kind', 'custom')
                    setValue('start_time', hhmm(suggestion.start.toISOString()))
                    setValue('end_time', hhmm(suggestion.end.toISOString()))
                    setSuggestion(null)
                    setFormError(null)
                  }}
                >
                  {hhmm(suggestion.start.toISOString())} – {hhmm(suggestion.end.toISOString())}
                </Button>
              </span>
            )}
          </Alert>
        )}

        <div>
          <Label htmlFor="booking-date">Date</Label>
          <Input
            id="booking-date"
            type="date"
            min={toIsoDate(new Date())}
            aria-invalid={errors.date !== undefined}
            aria-describedby={errors.date !== undefined ? 'booking-date-error' : undefined}
            {...register('date')}
          />
          {errors.date && (
            <p id="booking-date-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
              {errors.date.message}
            </p>
          )}
        </div>

        <fieldset>
          <legend className="mb-1 block text-sm font-medium text-neutral-800 dark:text-neutral-200">
            Créneau
          </legend>
          <div className="space-y-1">
            {kinds.map((value) => (
              <span key={value} className="flex items-center gap-2 text-sm">
                <input
                  id={`booking-kind-${value}`}
                  type="radio"
                  value={value}
                  className="size-4"
                  {...register('kind')}
                />
                <label htmlFor={`booking-kind-${value}`}>{KIND_LABELS[value]}</label>
              </span>
            ))}
          </div>
        </fieldset>

        {!isExternal && kind === 'custom' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="booking-start">Heure de début</Label>
              <Input
                id="booking-start"
                type="time"
                step={900}
                aria-invalid={errors.start_time !== undefined}
                aria-describedby={
                  errors.start_time !== undefined ? 'booking-start-error' : undefined
                }
                {...register('start_time')}
              />
              {errors.start_time && (
                <p id="booking-start-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
                  {errors.start_time.message}
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="booking-end">Heure de fin</Label>
              <Input
                id="booking-end"
                type="time"
                step={900}
                aria-invalid={errors.end_time !== undefined}
                aria-describedby={errors.end_time !== undefined ? 'booking-end-error' : undefined}
                {...register('end_time')}
              />
              {errors.end_time && (
                <p id="booking-end-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
                  {errors.end_time.message}
                </p>
              )}
            </div>
          </div>
        )}

        <div>
          <Label htmlFor="booking-title">Libellé (optionnel)</Label>
          <Input
            id="booking-title"
            maxLength={255}
            placeholder="Réunion équipe…"
            aria-invalid={errors.title !== undefined}
            aria-describedby={errors.title !== undefined ? 'booking-title-error' : undefined}
            {...register('title')}
          />
          {errors.title && (
            <p id="booking-title-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
              {errors.title.message}
            </p>
          )}
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Visible par les autres membres dans le calendrier.
          </p>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <Button type="submit" disabled={isSubmitting}>
            {target.mode === 'create' ? 'Réserver' : 'Enregistrer les modifications'}
          </Button>
          {target.mode === 'edit' && target.cancellable && (
            <ConfirmButton
              variant="danger"
              size="md"
              disabled={cancelBooking.isPending}
              confirmMessage="Supprimer cette réservation ?"
              confirmLabel="Oui, supprimer"
              cancelLabel="Non"
              onConfirm={() => void onDelete()}
            >
              Supprimer
            </ConfirmButton>
          )}
        </div>
      </form>
    </Modal>
  )
}

import { CalendarOff } from 'lucide-react'
import { useRef, useState } from 'react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/EmptyState'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { ConfirmButton } from '@/components/ui/confirm-button'
import { Spinner } from '@/components/ui/spinner'
import { getApiErrorMessage, getApiFieldErrors } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { AbsenceForm, WEEKDAYS } from './AbsenceForm'
import type { Absence, AbsencePeriod, CreateAbsenceInput } from './types'
import { useCreateAbsence, useDeleteAbsence, usePresence, useUpdateAbsence } from './usePresence'

const PERIOD_LABELS: Record<AbsencePeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

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

/** Résumé lisible d'une absence, réutilisé par les libellés d'action. */
function absenceSummary(absence: Absence): string {
  if (absence.recurrence_type === 'weekly' && absence.recurrence_day_of_week !== null) {
    const day = WEEKDAYS[absence.recurrence_day_of_week]?.toLowerCase() ?? 'semaine'
    const until = absence.date_end === null ? '' : ` jusqu’au ${formatDate(absence.date_end)}`
    return `chaque ${day}${until}`
  }
  if (absence.date_end !== null && absence.date_end !== absence.date_start) {
    return `du ${formatDate(absence.date_start)} au ${formatDate(absence.date_end)}`
  }
  return `du ${formatDate(absence.date_start)}`
}

/**
 * Module réservé au membre doté d'un bureau attitré : la garde est portée par
 * la route (<RequireAccess requiresDesk>), pas par la page.
 */
export function PresencePage() {
  usePageTitle('Ma présence — Portail Ecoworking')

  return <PresenceContent />
}

function PresenceContent() {
  const [range] = useState(defaultRange)
  const [showHistory, setShowHistory] = useState(false)
  const { data, isLoading, isError, refetch } = usePresence(range.from, range.to, showHistory)
  const createAbsence = useCreateAbsence()
  const updateAbsence = useUpdateAbsence()
  const deleteAbsence = useDeleteAbsence()
  // `null` = formulaire fermé ; `{ absence: null }` = déclaration ; sinon édition.
  const [editing, setEditing] = useState<{ absence: Absence | null } | null>(null)
  // Élément ayant ouvert le formulaire (« Marquer une absence » ou le bouton
  // « Modifier » d'une ligne) : le focus lui revient à la fermeture, plutôt
  // qu'au seul bouton de déclaration (RGAA, retour de contexte).
  const openerRef = useRef<HTMLElement | null>(null)

  function openForm(absence: Absence | null, opener: HTMLElement | null) {
    openerRef.current = opener
    setEditing({ absence })
  }

  function closeForm() {
    setEditing(null)
    openerRef.current?.focus()
  }

  async function onSubmit(payload: CreateAbsenceInput) {
    const target = editing?.absence ?? null
    try {
      if (target === null) {
        await createAbsence.mutateAsync(payload)
        toast.success('Absence enregistrée.')
      } else {
        await updateAbsence.mutateAsync({ id: target.id, input: payload })
        toast.success('Absence modifiée.')
      }
      closeForm()
    } catch (error) {
      // `AbsenceForm` rattache déjà les erreurs 422 à leurs champs (son propre
      // catch, plus bas) : un toast en plus ferait lire le même message deux
      // fois (review). On ne toaste que ce que le formulaire ne montre pas déjà.
      if (Object.keys(getApiFieldErrors(error)).length === 0) {
        toast.error(getApiErrorMessage(error, 'Enregistrement impossible.'))
      }
      throw error // le formulaire route les erreurs 422 vers ses champs
    }
  }

  async function onDelete(absence: Absence) {
    try {
      await deleteAbsence.mutateAsync(absence.id)
      toast.success('Absence supprimée.')
    } catch (error) {
      toast.error(getApiErrorMessage(error, 'Suppression impossible.'))
    }
  }

  const desk = data?.desk ?? null

  return (
    <PageContainer width="wide" className="space-y-10">
      <div className="space-y-2">
        <PageHeader title="Ma présence" />
        {desk !== null && (
          <p className="text-sm text-neutral-600 dark:text-neutral-300">
            Votre bureau attitré : <strong className="font-medium">{desk.name}</strong>
            {desk.floor !== null && <> — étage {desk.floor}</>}
          </p>
        )}
      </div>

      <section aria-labelledby="absence-form-heading" className="space-y-4">
        <h2 id="absence-form-heading" className="text-lg font-medium">
          {editing?.absence ? 'Modifier une absence' : 'Déclarer une absence'}
        </h2>
        <p className="text-sm text-neutral-500 dark:text-neutral-400">
          Signalez vos jours d’absence pour libérer votre bureau aux membres nomades.
        </p>

        <Button
          // `aria-expanded` ne décrit QUE le formulaire de déclaration : en
          // modification, ce bouton rouvre une déclaration vierge.
          aria-expanded={editing !== null && editing.absence === null}
          aria-controls="absence-form"
          onClick={(event) => {
            if (editing !== null && editing.absence === null) {
              closeForm()
              return
            }
            openForm(null, event.currentTarget)
          }}
        >
          Marquer une absence
        </Button>

        {editing !== null && (
          <div id="absence-form">
            <AbsenceForm
              absence={editing.absence}
              submitting={createAbsence.isPending || updateAbsence.isPending}
              onSubmit={onSubmit}
              onCancel={() => closeForm()}
            />
          </div>
        )}
      </section>

      <section aria-labelledby="absence-list-heading" className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="absence-list-heading" className="text-lg font-medium">
            {showHistory ? 'Toutes mes absences' : 'Mes absences à venir'}
          </h2>
          <Button
            variant="outline"
            size="sm"
            aria-pressed={showHistory}
            onClick={() => setShowHistory(!showHistory)}
          >
            {showHistory ? 'Masquer l’historique' : 'Voir l’historique'}
          </Button>
        </div>

        {isLoading && <Spinner label="Chargement de vos absences…" />}
        {isError && (
          <QueryError
            message="Impossible de charger vos absences."
            onRetry={() => void refetch()}
          />
        )}

        {data && data.absences.length === 0 && (
          <EmptyState
            icon={CalendarOff}
            title={showHistory ? 'Aucune absence déclarée.' : 'Aucune absence à venir.'}
          />
        )}

        {data && data.absences.length > 0 && (
          <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
            {data.absences.map((absence) => (
              <li
                key={absence.id}
                className="flex flex-wrap items-center justify-between gap-4 px-4 py-3"
              >
                <span className="text-sm">
                  <span className="block font-medium first-letter:uppercase">
                    {absenceSummary(absence)}
                  </span>
                  <span className="text-neutral-500 dark:text-neutral-400">
                    {PERIOD_LABELS[absence.period]}
                  </span>
                  {absence.notes && (
                    <span className="block text-xs text-neutral-500 dark:text-neutral-400">
                      {absence.notes}
                    </span>
                  )}
                  {/* Rappel utile uniquement sur la liste « à venir » : dans
                      l'historique, toutes les lignes passées sont verrouillées. */}
                  {!absence.can_edit && !showHistory && (
                    <span className="block text-xs text-neutral-500 dark:text-neutral-400">
                      {absence.can_delete
                        ? 'Absence commencée : vous pouvez encore la supprimer aujourd’hui.'
                        : 'Absence commencée : contactez l’accueil pour la modifier.'}
                    </span>
                  )}
                </span>
                <span className="flex flex-wrap items-center gap-2">
                  {absence.can_edit && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={(event) => openForm(absence, event.currentTarget)}
                    >
                      Modifier
                      <span className="sr-only"> l’absence {absenceSummary(absence)}</span>
                    </Button>
                  )}
                  {absence.can_delete && (
                    <ConfirmButton
                      variant="destructive"
                      size="sm"
                      disabled={deleteAbsence.isPending}
                      confirmMessage="Supprimer cette absence ?"
                      confirmLabel="Oui, supprimer"
                      cancelLabel="Non"
                      onConfirm={() => void onDelete(absence)}
                    >
                      Supprimer
                      <span className="sr-only"> l’absence {absenceSummary(absence)}</span>
                    </ConfirmButton>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </PageContainer>
  )
}

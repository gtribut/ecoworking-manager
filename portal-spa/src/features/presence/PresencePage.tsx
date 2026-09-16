import { Armchair, CalendarOff } from 'lucide-react'
import { useRef, useState } from 'react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/EmptyState'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ConfirmButton } from '@/components/ui/confirm-button'
import { Skeleton } from '@/components/ui/skeleton'
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

/** Date locale au format YYYY-MM-DD (pas d'UTC). */
function isoOf(date: Date): string {
  const offset = date.getTimezoneOffset()
  return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 10)
}

/** Fenêtre par défaut : du début du mois courant à 3 mois plus tard. */
function defaultRange(): { from: string; to: string } {
  const now = new Date()
  const from = new Date(now.getFullYear(), now.getMonth(), 1)
  const to = new Date(now.getFullYear(), now.getMonth() + 3, 0)
  return { from: isoOf(from), to: isoOf(to) }
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR')
}

/**
 * Libellé du badge « état du jour » quand une absence couvre aujourd'hui
 * (review I-2) — distinct de `PERIOD_LABELS` (utilisé pour les lignes de la
 * liste), au singulier « ce matin / cet après-midi » plutôt que « Matin ».
 */
const ABSENCE_TODAY_LABELS: Record<AbsencePeriod, string> = {
  morning: 'Absent(e) ce matin',
  afternoon: 'Absent(e) cet après-midi',
  full_day: 'Absent(e) aujourd’hui',
}

/**
 * Absence couvrant la date donnée, si elle existe (review I-2) : le back
 * (`PresenceService::presentOn()`) renvoie `false` dans `present_days` pour
 * TOUT jour non travaillé (week-end, férié, absence) — on ne peut donc pas
 * en déduire « absent » sans vérifier qu'une absence déclarée couvre bien le
 * jour, sous peine d'afficher « Absent(e) » un samedi ou un jour férié.
 * Cherche dans la liste déjà chargée par `usePresence()` (pas de nouvel
 * appel), plage simple ou récurrence hebdomadaire bornée (PRD §3.4.6).
 */
function findAbsenceCovering(absences: Absence[], dateIso: string): Absence | null {
  if (absences.length === 0) return null
  const dayOfWeek = new Date(`${dateIso}T12:00:00`).getDay()

  return (
    absences.find((absence) => {
      if (dateIso < absence.date_start) return false
      if (absence.recurrence_type === 'weekly') {
        if (absence.recurrence_day_of_week !== dayOfWeek) return false
        return absence.date_end === null || dateIso <= absence.date_end
      }
      return dateIso <= (absence.date_end ?? absence.date_start)
    }) ?? null
  )
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
  const today = isoOf(new Date())
  // « État du jour » (PRD §3.4.6/§3.7.4) : `present_days` (déjà renvoyé par
  // `/api/presence`, pas encore affiché avant ce lot) vaut `false` pour tout
  // jour non travaillé (week-end, férié, absence — cf. `PresenceService::
  // presentOn()`), pas seulement pour une absence déclarée : on ne peut donc
  // afficher « Absent(e) » que si une absence de la liste couvre bien
  // aujourd'hui (review I-2), sinon aucun badge (jour non ouvré/indéterminé).
  const presentToday = data ? data.present_days.includes(today) : null
  const todaysAbsence = data ? findAbsenceCovering(data.absences, today) : null

  return (
    <PageContainer width="wide" className="space-y-10">
      <PageHeader title="Ma présence" />

      <section aria-labelledby="my-desk-heading" className="space-y-4">
        <h2 id="my-desk-heading" className="text-lg font-medium">
          Mon bureau
        </h2>

        {isLoading && (
          <div role="status">
            <span className="sr-only">Chargement de votre bureau…</span>
            <Skeleton className="h-20 w-full" />
          </div>
        )}

        {data && desk && (
          <Card>
            <CardContent className="flex items-center gap-3">
              <Armchair className="size-5 shrink-0 text-primary" aria-hidden="true" />
              <p>
                {/* Libellé repris tel quel de la description posée par U2 dans
                    le `PageHeader` (remontée ici, pas dupliquée — cf.
                    portal-spa/CLAUDE.md « Shell du portail »). */}
                <span className="block font-medium">
                  Votre bureau attitré : <strong className="font-medium">{desk.name}</strong>
                  {desk.floor !== null && <> — étage {desk.floor}</>}
                </span>
                {presentToday === true && (
                  <Badge className="mt-1 bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200">
                    Présent(e) aujourd’hui
                  </Badge>
                )}
                {presentToday === false && todaysAbsence && (
                  <Badge
                    // `bg-muted`/`text-muted-foreground` ne fait que 4,35:1 en
                    // clair (review axe) : neutral-200/700 à la place.
                    className="mt-1 bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                  >
                    {ABSENCE_TODAY_LABELS[todaysAbsence.period]}
                  </Badge>
                )}
              </p>
            </CardContent>
          </Card>
        )}
      </section>

      <section aria-labelledby="absence-form-heading" className="space-y-4">
        <h2 id="absence-form-heading" className="text-lg font-medium">
          {editing?.absence ? 'Modifier une absence' : 'Déclarer une absence'}
        </h2>
        <p className="text-sm text-muted-foreground">
          Signalez vos jours d’absence pour libérer votre bureau aux membres nomades.
        </p>

        {/* Sous `data &&` (pas `desk &&`, review I-3) : un admin avec
            `declare-presence-for-others` mais sans bureau attitré (desk:
            null) garde la fonction — seule la carte « Mon bureau » ci-dessus
            dépend d'un bureau. */}
        {data && (
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
        )}

        {editing !== null && (
          <div id="absence-form">
            <Card>
              <CardContent>
                <AbsenceForm
                  absence={editing.absence}
                  submitting={createAbsence.isPending || updateAbsence.isPending}
                  onSubmit={onSubmit}
                  onCancel={() => closeForm()}
                />
              </CardContent>
            </Card>
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

        {isLoading && (
          <div role="status" className="space-y-2">
            <span className="sr-only">Chargement de vos absences…</span>
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        )}
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
          <Card>
            <CardContent className="px-0">
              <ul>
                {data.absences.map((absence) => (
                  <li
                    key={absence.id}
                    className="flex flex-wrap items-center justify-between gap-4 border-t px-4 py-3 first:border-t-0"
                  >
                    <span className="text-sm">
                      <span className="block font-medium first-letter:uppercase">
                        {absenceSummary(absence)}
                      </span>
                      <span className="text-muted-foreground">{PERIOD_LABELS[absence.period]}</span>
                      {absence.notes && (
                        <span className="block text-xs text-muted-foreground">{absence.notes}</span>
                      )}
                      {/* Rappel utile uniquement sur la liste « à venir » : dans
                          l'historique, toutes les lignes passées sont verrouillées. */}
                      {!absence.can_edit && !showHistory && (
                        <span className="block text-xs text-muted-foreground">
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
            </CardContent>
          </Card>
        )}
      </section>
    </PageContainer>
  )
}

import { Check, Copy } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmButton } from '@/components/ui/ConfirmButton'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import {
  useCalendarSubscription,
  useRegenerateCalendarToken,
  useRevokeCalendarToken,
} from './useCalendar'

/**
 * Abonnement agenda iCal (PRD §3.5.8) : URLs à copier dans Google Agenda /
 * Apple Calendar, régénération (révoque les anciens abonnements) et désactivation.
 * Flux en lecture seule, rafraîchis par le client agenda à son rythme.
 */
export function CalendarSubscription() {
  const { data, isLoading } = useCalendarSubscription()
  const regenerate = useRegenerateCalendarToken()
  const revoke = useRevokeCalendarToken()

  return (
    <section aria-labelledby="calendar-heading" className="space-y-4">
      <div>
        <h2 id="calendar-heading" className="text-lg font-medium">
          Abonnement agenda
        </h2>
        <p className="text-sm text-neutral-600 dark:text-neutral-300">
          Ajoutez ces liens dans Google Agenda ou Apple Calendar pour suivre les réservations de
          salles. Le rafraîchissement est géré par votre agenda (quelques heures pour Google).
        </p>
      </div>

      {isLoading ? (
        <p className="text-sm text-neutral-500 dark:text-neutral-400">Chargement…</p>
      ) : data?.enabled && data.urls ? (
        <div className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
          <FeedField id="feed-mine" label="Mes réservations" url={data.urls.mine} />
          <FeedField id="feed-entity" label="Réservations de mon entité" url={data.urls.entity} />

          <div className="flex flex-wrap gap-3 pt-2">
            <ConfirmButton
              variant="secondary"
              size="sm"
              confirmMessage="Les anciens liens seront invalidés immédiatement."
              confirmLabel="Oui, régénérer"
              cancelLabel="Non"
              disabled={regenerate.isPending}
              onConfirm={() => regenerate.mutate()}
            >
              Régénérer les liens
            </ConfirmButton>
            <ConfirmButton
              variant="ghost"
              size="sm"
              confirmMessage="Vos agendas ne se mettront plus à jour."
              confirmLabel="Oui, désactiver"
              cancelLabel="Non"
              disabled={revoke.isPending}
              onConfirm={() => revoke.mutate()}
            >
              Désactiver l’abonnement
            </ConfirmButton>
          </div>
          <p className="text-xs text-neutral-500 dark:text-neutral-400">
            Régénérer invalide immédiatement les anciens liens d’abonnement.
          </p>
        </div>
      ) : (
        <div className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
          <Button size="sm" onClick={() => regenerate.mutate()} disabled={regenerate.isPending}>
            Activer l’abonnement agenda
          </Button>
        </div>
      )}
    </section>
  )
}

function FeedField({ id, label, url }: { id: string; label: string; url: string }) {
  const [copied, setCopied] = useState(false)
  const [copyFailed, setCopyFailed] = useState(false)
  const timeoutRef = useRef<number | null>(null)

  // Nettoie le timer de retour à « Copier » si le composant est démonté avant.
  useEffect(() => {
    return () => {
      if (timeoutRef.current !== null) window.clearTimeout(timeoutRef.current)
    }
  }, [])

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      setCopyFailed(false)
      if (timeoutRef.current !== null) window.clearTimeout(timeoutRef.current)
      timeoutRef.current = window.setTimeout(() => setCopied(false), 2000)
    } catch {
      setCopied(false)
      setCopyFailed(true)
    }
  }

  return (
    <div>
      <Label htmlFor={id}>{label}</Label>
      <div className="flex gap-2">
        <Input id={id} value={url} readOnly className="font-mono text-xs" />
        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={() => void copy()}
          aria-label={`Copier le lien « ${label} »`}
        >
          {copied ? (
            <>
              <Check className="size-4" aria-hidden="true" />
              <span>Copié</span>
            </>
          ) : (
            <>
              <Copy className="size-4" aria-hidden="true" />
              <span>Copier</span>
            </>
          )}
        </Button>
      </div>
      {/* Annonce le passage « Copié » aux lecteurs d'écran. */}
      <span aria-live="polite" className="sr-only">
        {copied ? `Lien « ${label} » copié dans le presse-papiers.` : ''}
      </span>
      {copyFailed && (
        <p className="mt-1 text-sm text-red-600" role="alert">
          Copie impossible : sélectionnez le lien et copiez-le manuellement.
        </p>
      )}
    </div>
  )
}

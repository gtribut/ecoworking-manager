import { Check, Copy } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'
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
        <p className="text-sm text-neutral-500">Chargement…</p>
      ) : data?.enabled && data.urls ? (
        <div className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
          <FeedField id="feed-mine" label="Mes réservations" url={data.urls.mine} />
          <FeedField id="feed-entity" label="Réservations de mon entité" url={data.urls.entity} />

          <div className="flex flex-wrap gap-3 pt-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => regenerate.mutate()}
              disabled={regenerate.isPending}
            >
              Régénérer les liens
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => revoke.mutate()}
              disabled={revoke.isPending}
            >
              Désactiver l’abonnement
            </Button>
          </div>
          <p className="text-xs text-neutral-500">
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

  const copy = async () => {
    await navigator.clipboard.writeText(url)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
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
    </div>
  )
}

import { useState } from 'react'
import { Alert } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { ConfirmButton } from '@/components/ui/confirm-button'
import { usePermissions } from '@/features/auth/usePermissions'
import { getApiErrorMessage } from '@/lib/errors'
import type { Announcement } from './types'
import { useRegisterToEvent, useUnregisterFromEvent } from './useAnnouncements'

/**
 * Inscription / désinscription à un événement (PRD §2.5). Le serveur reste
 * l'autorité (jauge, date, audience, permission) : le bouton n'est qu'un
 * confort d'UI et affiche l'erreur métier 422 renvoyée par l'API.
 */
export function RsvpButton({ announcement }: { announcement: Announcement }) {
  const { has } = usePermissions()
  const register = useRegisterToEvent()
  const unregister = useUnregisterFromEvent()
  const [error, setError] = useState<string | null>(null)

  // Annonce sans inscription, ou membre sans la permission (billing_contact pur).
  if (!announcement.requires_registration || !has('register-event')) {
    return null
  }

  const pending = register.isPending || unregister.isPending

  const handleRegister = () => {
    setError(null)
    register.mutate(announcement.id, {
      onError: (mutationError) =>
        setError(getApiErrorMessage(mutationError, "L'inscription a échoué.")),
    })
  }

  const handleUnregister = () => {
    setError(null)
    unregister.mutate(announcement.id, {
      onError: (mutationError) =>
        setError(getApiErrorMessage(mutationError, 'La désinscription a échoué.')),
    })
  }

  return (
    <div className="space-y-2">
      {announcement.is_registered ? (
        <div className="flex flex-wrap items-center gap-3">
          <p className="text-sm font-medium text-green-700 dark:text-green-300" role="status">
            Vous êtes inscrit(e) à cet événement.
          </p>
          <ConfirmButton
            variant="outline"
            size="sm"
            disabled={pending}
            confirmMessage="Annuler votre inscription ?"
            onConfirm={handleUnregister}
          >
            Me désinscrire
          </ConfirmButton>
        </div>
      ) : announcement.is_registrable ? (
        <Button size="sm" disabled={pending} onClick={handleRegister}>
          M’inscrire à l’événement
        </Button>
      ) : (
        <p className="text-sm text-neutral-500 dark:text-neutral-400" role="status">
          {announcement.spots_left === 0
            ? 'Événement complet.'
            : 'Les inscriptions sont closes pour cet événement.'}
        </p>
      )}

      {error && <Alert variant="error">{error}</Alert>}
    </div>
  )
}

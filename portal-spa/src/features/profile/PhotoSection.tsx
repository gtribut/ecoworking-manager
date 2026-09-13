import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useId, useRef, useState } from 'react'
import { Avatar, type PhotoUrls } from '@/components/Avatar'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Label } from '@/components/ui/Label'
import { getApiErrorMessage, getApiFieldErrors } from '@/lib/errors'
import {
  deleteProfilePhoto,
  PHOTO_ACCEPTED_TYPES,
  type PhotoResponse,
  uploadProfilePhoto,
  validatePhotoFile,
} from './photoApi'
import type { ProfilePayload } from './types'
import { profileQueryKey } from './useProfile'

interface PhotoSectionProps {
  firstName: string
  lastName: string
  photo: PhotoUrls | null
}

/**
 * Photo de profil (PRD §3.4.2) : aperçu (photo ou avatar initiales), dépôt
 * d'un fichier et suppression. Le redimensionnement carré, la conversion et
 * le retrait des EXIF sont faits côté serveur — le client ne fait que la
 * première barrière (type + poids), jamais la seule (CLAUDE.md §3.2).
 */
export function PhotoSection({ firstName, lastName, photo }: PhotoSectionProps) {
  const inputId = useId()
  const inputRef = useRef<HTMLInputElement>(null)
  const queryClient = useQueryClient()
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null,
  )

  /** Répercute la nouvelle photo dans le profil déjà en cache. */
  const applyPhoto = (payload: PhotoResponse) => {
    queryClient.setQueryData(profileQueryKey, (current: ProfilePayload | undefined) =>
      current?.profile
        ? { ...current, profile: { ...current.profile, photo: payload.photo } }
        : current,
    )
  }

  const upload = useMutation({
    mutationFn: uploadProfilePhoto,
    onSuccess: (payload) => {
      applyPhoto(payload)
      setFeedback({ type: 'success', message: 'Photo de profil mise à jour.' })
    },
    onError: (error) => {
      const fieldErrors = getApiFieldErrors(error)
      setFeedback({
        type: 'error',
        message: fieldErrors.photo ?? getApiErrorMessage(error, 'Envoi de la photo impossible.'),
      })
    },
    onSettled: () => {
      if (inputRef.current) inputRef.current.value = ''
    },
  })

  const remove = useMutation({
    mutationFn: deleteProfilePhoto,
    onSuccess: (payload) => {
      applyPhoto(payload)
      setFeedback({ type: 'success', message: 'Photo de profil supprimée.' })
    },
    onError: (error) => {
      setFeedback({
        type: 'error',
        message: getApiErrorMessage(error, 'Suppression de la photo impossible.'),
      })
    },
  })

  const busy = upload.isPending || remove.isPending

  return (
    <section
      aria-labelledby="photo-heading"
      className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
    >
      <h2 id="photo-heading" className="text-lg font-medium">
        Photo de profil
      </h2>

      {feedback && <Alert variant={feedback.type}>{feedback.message}</Alert>}

      <div className="flex flex-wrap items-center gap-6">
        <Avatar firstName={firstName} lastName={lastName} photo={photo} size="lg" />

        <div className="space-y-3">
          <div>
            <Label htmlFor={inputId}>Choisir une photo</Label>
            <input
              id={inputId}
              ref={inputRef}
              type="file"
              accept={PHOTO_ACCEPTED_TYPES.join(',')}
              disabled={busy}
              aria-describedby={`${inputId}-hint`}
              className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:bg-neutral-800 dark:file:text-brand-50"
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (!file) return

                setFeedback(null)
                const invalid = validatePhotoFile(file)
                if (invalid) {
                  setFeedback({ type: 'error', message: invalid })
                  event.target.value = ''
                  return
                }

                upload.mutate(file)
              }}
            />
            <p
              id={`${inputId}-hint`}
              className="mt-1 text-sm text-neutral-600 dark:text-neutral-300"
            >
              JPG, PNG ou WebP, 2 Mo maximum. L’image est recadrée en carré automatiquement.
            </p>
          </div>

          {photo && (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={busy}
              onClick={() => {
                setFeedback(null)
                remove.mutate()
              }}
            >
              Supprimer la photo
            </Button>
          )}
        </div>
      </div>
    </section>
  )
}

import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { getApiErrorMessage, getApiFieldErrors } from '@/lib/errors'
import { updatePassword } from './passwordApi'

// Aligné sur la politique back (`Password::default()`, non personnalisée →
// minimum 8 caractères, cf. PasswordValidationRules). Le Zod front double la
// validation sans jamais la remplacer (CLAUDE.md §3.2).
const schema = z
  .object({
    current_password: z.string().min(1, 'Le mot de passe actuel est requis.'),
    password: z.string().min(8, '8 caractères minimum.'),
    password_confirmation: z.string().min(1, 'Confirmez le nouveau mot de passe.'),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'La confirmation ne correspond pas au nouveau mot de passe.',
    path: ['password_confirmation'],
  })

type FormValues = z.infer<typeof schema>

const defaultValues: FormValues = {
  current_password: '',
  password: '',
  password_confirmation: '',
}

/**
 * Changement de mot de passe depuis le portail (PRD §3.4.2 / §3.4.5) :
 * ré-authentification obligatoire (mot de passe actuel), erreurs 422
 * rattachées au champ concerné, champs vidés après succès (pas de toast,
 * lot G — feedback en `<Alert>` inline).
 */
export function PasswordSection() {
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null,
  )

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues })
  const { errors } = form.formState

  const onSubmit = form.handleSubmit(async (values) => {
    setFeedback(null)
    try {
      await updatePassword(values)
      form.reset(defaultValues)
      setFeedback({ type: 'success', message: 'Mot de passe modifié.' })
    } catch (error) {
      const fieldErrors = getApiFieldErrors(error)
      if (Object.keys(fieldErrors).length > 0) {
        for (const [field, message] of Object.entries(fieldErrors)) {
          if (
            field === 'current_password' ||
            field === 'password' ||
            field === 'password_confirmation'
          ) {
            form.setError(field, { message })
          }
        }
      } else {
        setFeedback({ type: 'error', message: getApiErrorMessage(error) })
      }
    }
  })

  return (
    <section
      aria-labelledby="password-heading"
      className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
    >
      <h2 id="password-heading" className="text-lg font-medium">
        Mot de passe
      </h2>

      {feedback && <Alert variant={feedback.type}>{feedback.message}</Alert>}

      <form
        onSubmit={(event) => {
          void onSubmit(event)
        }}
        className="space-y-4"
        noValidate
      >
        <div>
          <Label htmlFor="current_password">Mot de passe actuel</Label>
          <Input
            id="current_password"
            type="password"
            autoComplete="current-password"
            aria-invalid={Boolean(errors.current_password)}
            aria-describedby={errors.current_password ? 'current_password-error' : undefined}
            {...form.register('current_password')}
          />
          {errors.current_password && (
            <p id="current_password-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
              {errors.current_password.message}
            </p>
          )}
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <Label htmlFor="new_password">Nouveau mot de passe</Label>
            <Input
              id="new_password"
              type="password"
              autoComplete="new-password"
              aria-invalid={Boolean(errors.password)}
              aria-describedby={errors.password ? 'new_password-error' : undefined}
              {...form.register('password')}
            />
            {errors.password && (
              <p id="new_password-error" className="mt-1 text-sm text-red-700 dark:text-red-300">
                {errors.password.message}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="password_confirmation">Confirmer le nouveau mot de passe</Label>
            <Input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              aria-invalid={Boolean(errors.password_confirmation)}
              aria-describedby={
                errors.password_confirmation ? 'password_confirmation-error' : undefined
              }
              {...form.register('password_confirmation')}
            />
            {errors.password_confirmation && (
              <p
                id="password_confirmation-error"
                className="mt-1 text-sm text-red-700 dark:text-red-300"
              >
                {errors.password_confirmation.message}
              </p>
            )}
          </div>
        </div>

        <Button type="submit" disabled={form.formState.isSubmitting}>
          Modifier mon mot de passe
        </Button>
      </form>
    </section>
  )
}

import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { resetPassword } from './api'

const schema = z
  .object({
    email: z.string().min(1, 'L’email est requis.').email('Email invalide.'),
    password: z.string().min(8, 'Au moins 8 caractères.'),
    password_confirmation: z.string().min(1, 'Confirmez le mot de passe.'),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Les deux mots de passe ne correspondent pas.',
    path: ['password_confirmation'],
  })
type FormValues = z.infer<typeof schema>

/**
 * Page cible du lien « mot de passe oublié » (PRD §3.2, recette R-03) :
 * `/reset-password/:token?email=…`, hors authentification. Le jeton est validé
 * côté Fortify (`POST /reset-password`, expiration 60 min) ; en cas de succès,
 * retour au login avec un message de confirmation.
 */
export function ResetPasswordPage() {
  usePageTitle('Nouveau mot de passe — Portail Ecoworking')

  const { token = '' } = useParams<{ token: string }>()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: searchParams.get('email') ?? '',
      password: '',
      password_confirmation: '',
    },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await resetPassword({ token, ...values })
      navigate('/login', {
        replace: true,
        state: { notice: 'Votre mot de passe a été modifié. Vous pouvez vous connecter.' },
      })
    } catch (error) {
      setFormError(
        getApiErrorMessage(
          error,
          'Réinitialisation impossible. Le lien est peut-être expiré : demandez-en un nouveau.',
        ),
      )
    }
  })

  const errors = form.formState.errors

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm space-y-6">
        <h1 className="text-center text-2xl font-semibold">Nouveau mot de passe</h1>

        {formError && <Alert variant="error">{formError}</Alert>}

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <div>
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              aria-invalid={Boolean(errors.email)}
              {...form.register('email')}
            />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>}
          </div>
          <div>
            <Label htmlFor="password">Nouveau mot de passe</Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              aria-invalid={Boolean(errors.password)}
              {...form.register('password')}
            />
            {errors.password && (
              <p className="mt-1 text-sm text-red-600">{errors.password.message}</p>
            )}
          </div>
          <div>
            <Label htmlFor="password_confirmation">Confirmation</Label>
            <Input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              aria-invalid={Boolean(errors.password_confirmation)}
              {...form.register('password_confirmation')}
            />
            {errors.password_confirmation && (
              <p className="mt-1 text-sm text-red-600">{errors.password_confirmation.message}</p>
            )}
          </div>
          <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
            Enregistrer le nouveau mot de passe
          </Button>
          <div className="text-center text-sm">
            <Link to="/login" className="underline">
              Retour à la connexion
            </Link>
          </div>
        </form>
      </div>
    </main>
  )
}

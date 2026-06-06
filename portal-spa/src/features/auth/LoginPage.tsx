import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useLocation, useNavigate } from 'react-router'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { getApiErrorMessage } from '@/lib/errors'
import { login, twoFactorChallenge } from './api'
import { useAuth } from './useAuth'

const loginSchema = z.object({
  email: z.string().min(1, 'L’email est requis.').email('Email invalide.'),
  password: z.string().min(1, 'Le mot de passe est requis.'),
})
type LoginValues = z.infer<typeof loginSchema>

const challengeSchema = z.object({
  code: z.string().min(6, 'Code à 6 chiffres requis.'),
})
type ChallengeValues = z.infer<typeof challengeSchema>

interface LocationState {
  from?: { pathname?: string }
}

export function LoginPage() {
  const { refetchUser } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [needsTwoFactor, setNeedsTwoFactor] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const target = (location.state as LocationState | null)?.from?.pathname ?? '/'

  async function finishLogin() {
    await refetchUser()
    navigate(target, { replace: true })
  }

  const loginForm = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const challengeForm = useForm<ChallengeValues>({
    resolver: zodResolver(challengeSchema),
    defaultValues: { code: '' },
  })

  const onLogin = loginForm.handleSubmit(async (values) => {
    setFormError(null)
    try {
      const result = await login(values)
      if (result.status === 'two-factor-required') {
        setNeedsTwoFactor(true)
        return
      }
      await finishLogin()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Connexion impossible.'))
    }
  })

  const onChallenge = challengeForm.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await twoFactorChallenge({ code: values.code })
      await finishLogin()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Code invalide.'))
    }
  })

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm space-y-6">
        <h1 className="text-center text-2xl font-semibold">Portail Ecoworking</h1>

        {formError && <Alert variant="error">{formError}</Alert>}

        {needsTwoFactor ? (
          <form onSubmit={onChallenge} className="space-y-4" noValidate>
            <p className="text-sm text-neutral-600 dark:text-neutral-300">
              Saisissez le code de votre application d’authentification.
            </p>
            <div>
              <Label htmlFor="code">Code de vérification</Label>
              <Input
                id="code"
                inputMode="numeric"
                autoComplete="one-time-code"
                aria-invalid={Boolean(challengeForm.formState.errors.code)}
                {...challengeForm.register('code')}
              />
              {challengeForm.formState.errors.code && (
                <p className="mt-1 text-sm text-red-600">
                  {challengeForm.formState.errors.code.message}
                </p>
              )}
            </div>
            <Button
              type="submit"
              className="w-full"
              disabled={challengeForm.formState.isSubmitting}
            >
              Vérifier
            </Button>
          </form>
        ) : (
          <form onSubmit={onLogin} className="space-y-4" noValidate>
            <div>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                aria-invalid={Boolean(loginForm.formState.errors.email)}
                {...loginForm.register('email')}
              />
              {loginForm.formState.errors.email && (
                <p className="mt-1 text-sm text-red-600">
                  {loginForm.formState.errors.email.message}
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="password">Mot de passe</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                aria-invalid={Boolean(loginForm.formState.errors.password)}
                {...loginForm.register('password')}
              />
              {loginForm.formState.errors.password && (
                <p className="mt-1 text-sm text-red-600">
                  {loginForm.formState.errors.password.message}
                </p>
              )}
            </div>
            <Button type="submit" className="w-full" disabled={loginForm.formState.isSubmitting}>
              Se connecter
            </Button>
          </form>
        )}
      </div>
    </main>
  )
}

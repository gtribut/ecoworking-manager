import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useLocation, useNavigate, useSearchParams } from 'react-router'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { login, requestMagicLink, twoFactorChallenge } from './api'
import { useAuth } from './useAuth'

const loginSchema = z.object({
  email: z.string().min(1, 'L’email est requis.').email('Email invalide.'),
  password: z.string().min(1, 'Le mot de passe est requis.'),
  remember: z.boolean(),
})
type LoginValues = z.infer<typeof loginSchema>

const challengeSchema = z.object({
  code: z.string().min(6, 'Code à 6 chiffres requis.'),
})
type ChallengeValues = z.infer<typeof challengeSchema>

const recoverySchema = z.object({
  recovery_code: z.string().min(1, 'Le code de récupération est requis.'),
})
type RecoveryValues = z.infer<typeof recoverySchema>

const magicLinkSchema = z.object({
  email: z.string().min(1, 'L’email est requis.').email('Email invalide.'),
})
type MagicLinkValues = z.infer<typeof magicLinkSchema>

type Step = 'login' | 'totp' | 'recovery' | 'magic-link'

interface LocationState {
  from?: { pathname?: string }
}

export function LoginPage() {
  usePageTitle('Connexion — Portail Ecoworking')

  const { refetchUser } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const [step, setStep] = useState<Step>('login')
  // Retour d'un magic link refusé (invalide, déjà utilisé ou expiré) : le back
  // redirige vers /login?magic_link=invalid sans détail exploitable.
  const [formError, setFormError] = useState<string | null>(() =>
    searchParams.get('magic_link') === 'invalid'
      ? 'Ce lien de connexion est invalide, déjà utilisé ou expiré. Vous pouvez en demander un nouveau.'
      : null,
  )
  const [magicLinkSent, setMagicLinkSent] = useState(false)

  const target = (location.state as LocationState | null)?.from?.pathname ?? '/'

  async function finishLogin() {
    await refetchUser()
    navigate(target, { replace: true })
  }

  const loginForm = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '', remember: false },
  })

  const challengeForm = useForm<ChallengeValues>({
    resolver: zodResolver(challengeSchema),
    defaultValues: { code: '' },
  })

  const recoveryForm = useForm<RecoveryValues>({
    resolver: zodResolver(recoverySchema),
    defaultValues: { recovery_code: '' },
  })

  const magicLinkForm = useForm<MagicLinkValues>({
    resolver: zodResolver(magicLinkSchema),
    defaultValues: { email: '' },
  })

  function goTo(nextStep: Step) {
    setFormError(null)
    challengeForm.reset()
    recoveryForm.reset()
    magicLinkForm.reset()
    setMagicLinkSent(false)
    setStep(nextStep)
  }

  const onLogin = loginForm.handleSubmit(async (values) => {
    setFormError(null)
    try {
      const result = await login(values)
      if (result.status === 'two-factor-required') {
        setStep('totp')
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

  const onRecovery = recoveryForm.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await twoFactorChallenge({ recovery_code: values.recovery_code })
      await finishLogin()
    } catch (error) {
      setFormError(getApiErrorMessage(error, 'Code de récupération invalide.'))
    }
  })

  const onMagicLink = magicLinkForm.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await requestMagicLink(values.email)
      setMagicLinkSent(true)
    } catch (error) {
      setFormError(
        getApiErrorMessage(error, 'Envoi impossible pour le moment. Réessayez dans un instant.'),
      )
    }
  })

  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm space-y-6">
        <h1 className="text-center text-2xl font-semibold">Portail Ecoworking</h1>

        {formError && <Alert variant="error">{formError}</Alert>}

        {step === 'totp' && (
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
            <div className="flex flex-col gap-2 text-center text-sm">
              <button
                type="button"
                className="text-brand-700 underline dark:text-brand-50"
                onClick={() => goTo('recovery')}
              >
                Utiliser un code de récupération
              </button>
              <button type="button" className="underline" onClick={() => goTo('login')}>
                Retour à la connexion
              </button>
            </div>
          </form>
        )}

        {step === 'recovery' && (
          <form onSubmit={onRecovery} className="space-y-4" noValidate>
            <p className="text-sm text-neutral-600 dark:text-neutral-300">
              Téléphone perdu ? Saisissez l’un des codes de récupération fournis à l’activation de
              la double authentification.
            </p>
            <div>
              <Label htmlFor="recovery_code">Code de récupération</Label>
              <Input
                id="recovery_code"
                autoComplete="off"
                aria-invalid={Boolean(recoveryForm.formState.errors.recovery_code)}
                {...recoveryForm.register('recovery_code')}
              />
              {recoveryForm.formState.errors.recovery_code && (
                <p className="mt-1 text-sm text-red-600">
                  {recoveryForm.formState.errors.recovery_code.message}
                </p>
              )}
            </div>
            <Button type="submit" className="w-full" disabled={recoveryForm.formState.isSubmitting}>
              Vérifier
            </Button>
            <div className="flex flex-col gap-2 text-center text-sm">
              <button
                type="button"
                className="text-brand-700 underline dark:text-brand-50"
                onClick={() => goTo('totp')}
              >
                Utiliser le code de l’application
              </button>
              <button type="button" className="underline" onClick={() => goTo('login')}>
                Retour à la connexion
              </button>
            </div>
          </form>
        )}

        {step === 'login' && (
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
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4" {...loginForm.register('remember')} />
              Se souvenir de moi
            </label>
            <Button type="submit" className="w-full" disabled={loginForm.formState.isSubmitting}>
              Se connecter
            </Button>
            <div className="text-center text-sm">
              <button
                type="button"
                className="text-brand-700 underline dark:text-brand-50"
                onClick={() => goTo('magic-link')}
              >
                Recevoir un lien de connexion par email
              </button>
            </div>
          </form>
        )}

        {step === 'magic-link' &&
          (magicLinkSent ? (
            <div className="space-y-4">
              {/* Message volontairement générique : ne révèle pas si l'email
                  correspond à un compte (anti-énumération, PRD §3.2). */}
              <Alert variant="success">
                Si un compte correspond à cette adresse, un lien de connexion vient de vous être
                envoyé par email. Il est valable 15 minutes et ne peut servir qu’une seule fois.
              </Alert>
              <div className="text-center text-sm">
                <button type="button" className="underline" onClick={() => goTo('login')}>
                  Retour à la connexion
                </button>
              </div>
            </div>
          ) : (
            <form onSubmit={onMagicLink} className="space-y-4" noValidate>
              <p className="text-sm text-neutral-600 dark:text-neutral-300">
                Recevez par email un lien de connexion à usage unique, sans saisir votre mot de
                passe.
              </p>
              <div>
                <Label htmlFor="magic-link-email">Email</Label>
                <Input
                  id="magic-link-email"
                  type="email"
                  autoComplete="email"
                  aria-invalid={Boolean(magicLinkForm.formState.errors.email)}
                  {...magicLinkForm.register('email')}
                />
                {magicLinkForm.formState.errors.email && (
                  <p className="mt-1 text-sm text-red-600">
                    {magicLinkForm.formState.errors.email.message}
                  </p>
                )}
              </div>
              <Button
                type="submit"
                className="w-full"
                disabled={magicLinkForm.formState.isSubmitting}
              >
                Recevoir le lien de connexion
              </Button>
              <div className="text-center text-sm">
                <button type="button" className="underline" onClick={() => goTo('login')}>
                  Retour à la connexion
                </button>
              </div>
            </form>
          ))}
      </div>
    </main>
  )
}

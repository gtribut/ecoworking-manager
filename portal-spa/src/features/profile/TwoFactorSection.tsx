import { type FormEvent, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/Button'
import { ConfirmButton } from '@/components/ui/ConfirmButton'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import {
  confirmPassword,
  confirmTwoFactor,
  disableTwoFactor,
  enableTwoFactor,
  fetchRecoveryCodes,
  fetchTwoFactorQrCode,
  fetchTwoFactorSecretKey,
  needsPasswordConfirmation,
  regenerateRecoveryCodes,
} from '@/features/auth/twoFactorApi'
import { useAuth } from '@/features/auth/useAuth'
import { getApiErrorMessage } from '@/lib/errors'

type Mode =
  | { kind: 'idle' }
  | { kind: 'confirm-password'; pending: () => Promise<void> }
  | { kind: 'setup'; qrSvg: string; secretKey: string }
  | { kind: 'recovery-codes'; codes: string[] }

/**
 * Double authentification TOTP du membre (PRD §3.2 : optionnelle, jamais
 * imposée — recette R-04). Activation : secret → QR code + clé → premier code
 * valide → codes de récupération. Fortify exige une confirmation récente du
 * mot de passe (423) avant toute mutation : l'action est mise en attente,
 * rejouée après saisie du mot de passe.
 */
export function TwoFactorSection() {
  const { user, refetchUser } = useAuth()
  const enabled = user?.two_factor_enabled === true

  const [mode, setMode] = useState<Mode>({ kind: 'idle' })
  const [busy, setBusy] = useState(false)
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')

  /** Exécute une action 2FA ; un 423 la met en attente derrière la confirmation du mot de passe. */
  async function run(action: () => Promise<void>) {
    setBusy(true)
    try {
      await action()
    } catch (err) {
      if (needsPasswordConfirmation(err)) {
        setMode({ kind: 'confirm-password', pending: action })
      } else {
        toast.error(getApiErrorMessage(err))
      }
    } finally {
      setBusy(false)
    }
  }

  const startSetup = () =>
    run(async () => {
      await enableTwoFactor()
      const [{ svg }, secretKey] = await Promise.all([
        fetchTwoFactorQrCode(),
        fetchTwoFactorSecretKey(),
      ])
      setCode('')
      setMode({ kind: 'setup', qrSvg: svg, secretKey })
    })

  const showRecoveryCodes = (regenerate: boolean) =>
    run(async () => {
      if (regenerate) await regenerateRecoveryCodes()
      const codes = await fetchRecoveryCodes()
      setMode({ kind: 'recovery-codes', codes })
    })

  const disable = () =>
    run(async () => {
      await disableTwoFactor()
      await refetchUser()
      setMode({ kind: 'idle' })
    })

  async function onConfirmPassword(event: FormEvent) {
    event.preventDefault()
    if (mode.kind !== 'confirm-password') return
    const { pending } = mode
    setBusy(true)
    try {
      await confirmPassword(password)
      setPassword('')
      setMode({ kind: 'idle' })
      await run(pending)
    } catch (err) {
      toast.error(getApiErrorMessage(err, 'Mot de passe incorrect.'))
    } finally {
      setBusy(false)
    }
  }

  async function onConfirmCode(event: FormEvent) {
    event.preventDefault()
    await run(async () => {
      await confirmTwoFactor(code)
      const codes = await fetchRecoveryCodes()
      await refetchUser()
      setMode({ kind: 'recovery-codes', codes })
      toast.success('Double authentification activée.')
    })
  }

  return (
    <section
      aria-labelledby="two-factor-heading"
      className="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="two-factor-heading" className="text-lg font-medium">
          Double authentification
        </h2>
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-medium ${
            enabled
              ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
              : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'
          }`}
        >
          {enabled ? 'Activée' : 'Désactivée'}
        </span>
      </div>
      <p className="text-sm text-neutral-500 dark:text-neutral-400">
        Optionnelle mais recommandée : un code à 6 chiffres généré par une application
        d’authentification (Google Authenticator, Aegis, 1Password…) vous sera demandé à chaque
        connexion.
      </p>

      {mode.kind === 'confirm-password' && (
        <form onSubmit={onConfirmPassword} className="space-y-3" noValidate>
          <p className="text-sm">Par sécurité, confirmez votre mot de passe pour continuer.</p>
          <div>
            <Label htmlFor="two-factor-password">Mot de passe actuel</Label>
            <Input
              id="two-factor-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
            />
          </div>
          <div className="flex gap-2">
            <Button type="submit" disabled={busy || password.length === 0}>
              Confirmer
            </Button>
            <Button type="button" variant="secondary" onClick={() => setMode({ kind: 'idle' })}>
              Annuler
            </Button>
          </div>
        </form>
      )}

      {mode.kind === 'setup' && (
        <form onSubmit={onConfirmCode} className="space-y-4" noValidate>
          <ol className="list-decimal space-y-3 pl-5 text-sm">
            <li>
              Scannez ce QR code avec votre application d’authentification :
              <img
                src={`data:image/svg+xml,${encodeURIComponent(mode.qrSvg)}`}
                alt="QR code d’activation de la double authentification"
                className="mt-2 size-48 rounded bg-white p-2"
              />
              <p className="mt-2">
                Impossible de scanner ? Saisissez cette clé :{' '}
                <code className="select-all rounded bg-neutral-100 px-1 py-0.5 dark:bg-neutral-800">
                  {mode.secretKey}
                </code>
              </p>
            </li>
            <li>
              <Label htmlFor="two-factor-code">Saisissez le code affiché par l’application</Label>
              <Input
                id="two-factor-code"
                inputMode="numeric"
                autoComplete="one-time-code"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                className="max-w-xs"
                required
              />
            </li>
          </ol>
          <div className="flex gap-2">
            <Button type="submit" disabled={busy || code.length < 6}>
              Activer
            </Button>
            <Button type="button" variant="secondary" onClick={() => setMode({ kind: 'idle' })}>
              Annuler
            </Button>
          </div>
        </form>
      )}

      {mode.kind === 'recovery-codes' && (
        <div className="space-y-3">
          <p className="text-sm">
            Conservez ces codes de récupération en lieu sûr : chacun permet de vous connecter une
            seule fois si vous perdez l’accès à votre application.
          </p>
          <ul
            className="grid grid-cols-2 gap-1 font-mono text-sm"
            aria-label="Codes de récupération"
          >
            {mode.codes.map((recoveryCode) => (
              <li key={recoveryCode} className="select-all">
                {recoveryCode}
              </li>
            ))}
          </ul>
          <Button type="button" variant="secondary" onClick={() => setMode({ kind: 'idle' })}>
            J’ai noté mes codes
          </Button>
        </div>
      )}

      {mode.kind === 'idle' && (
        <div className="flex flex-wrap gap-2">
          {enabled ? (
            <>
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={() => showRecoveryCodes(false)}
              >
                Afficher mes codes de récupération
              </Button>
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={() => showRecoveryCodes(true)}
              >
                Régénérer les codes
              </Button>
              <ConfirmButton
                variant="danger"
                disabled={busy}
                confirmMessage="Désactiver la double authentification ?"
                onConfirm={() => void disable()}
              >
                Désactiver
              </ConfirmButton>
            </>
          ) : (
            <Button type="button" disabled={busy} onClick={() => void startSetup()}>
              Activer la double authentification
            </Button>
          )}
        </div>
      )}
    </section>
  )
}

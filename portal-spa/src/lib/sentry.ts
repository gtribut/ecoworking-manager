import * as Sentry from '@sentry/react'

/**
 * Initialise Sentry côté SPA (C10.1, BRIEF §16). No-op si `VITE_SENTRY_DSN`
 * est absent (dev / tests) → aucune donnée transmise. Le DSN et le taux
 * d'échantillonnage sont injectés au build (variables d'env Vite).
 */
export function initSentry(): void {
  const dsn = import.meta.env.VITE_SENTRY_DSN

  if (!dsn) return

  Sentry.init({
    dsn,
    environment: import.meta.env.MODE,
    tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0.2),
    // Pas de PII automatique (RGPD §3.4) : on ne joint pas les en-têtes/cookies.
    sendDefaultPii: false,
  })
}

import { AxiosError } from 'axios'

interface LaravelErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Message d'erreur lisible issu d'une réponse Laravel (ou message générique). */
export function getApiErrorMessage(error: unknown, fallback = 'Une erreur est survenue.'): string {
  if (error instanceof AxiosError) {
    // Jamais de « CSRF token mismatch » brut : la session est simplement expirée.
    if (error.response?.status === 419) {
      return 'Votre session a expiré. Veuillez vous reconnecter.'
    }
    // Jamais de « Too Many Attempts. » brut (recette R-01) : le throttle Laravel
    // (login 5/min, API 60/min) n'est pas traduit côté serveur.
    if (error.response?.status === 429) {
      const retryAfter = Number(error.response.headers?.['retry-after'])
      return Number.isFinite(retryAfter) && retryAfter > 0
        ? `Trop de tentatives. Réessayez dans ${retryAfter} seconde${retryAfter > 1 ? 's' : ''}.`
        : 'Trop de tentatives. Réessayez dans quelques instants.'
    }
    // 403 : jamais le message serveur brut (PRD §3.8.2, lot G) — libellé
    // générique identique à l'écran <Forbidden> plutôt qu'une phrase métier
    // qui pourrait varier d'une policy à l'autre.
    if (error.response?.status === 403) {
      return 'Accès refusé.'
    }
    const body = error.response?.data as LaravelErrorBody | undefined
    if (body?.message) {
      return body.message
    }
    if (error.response?.status === 401) {
      return 'Identifiants invalides.'
    }
  }
  return fallback
}

/**
 * Erreurs de validation Laravel (422) indexées par champ, pour les afficher
 * sous les champs concernés (`aria-describedby`). Vide si la réponse n'est pas
 * une 422 avec un corps de validation.
 */
export function getApiFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof AxiosError) || error.response?.status !== 422) {
    return {}
  }
  const body = error.response.data as LaravelErrorBody | undefined
  const entries = Object.entries(body?.errors ?? {}).flatMap(([field, messages]) => {
    const first = messages[0]
    return first === undefined ? [] : [[field, first] as const]
  })
  return Object.fromEntries(entries)
}

/** Statut HTTP d'une erreur API, si disponible. */
export function getApiStatus(error: unknown): number | null {
  return error instanceof AxiosError ? (error.response?.status ?? null) : null
}

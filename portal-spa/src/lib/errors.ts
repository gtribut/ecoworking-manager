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

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

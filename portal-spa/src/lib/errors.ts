import { AxiosError } from 'axios'

interface LaravelErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Message d'erreur lisible issu d'une réponse Laravel (ou message générique). */
export function getApiErrorMessage(error: unknown, fallback = 'Une erreur est survenue.'): string {
  if (error instanceof AxiosError) {
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

/** Erreurs de validation par champ (statut 422 Laravel), aplaties en un message. */
export function getValidationErrors(error: unknown): Record<string, string> {
  if (error instanceof AxiosError && error.response?.status === 422) {
    const errors = (error.response.data as LaravelErrorBody).errors ?? {}
    return Object.fromEntries(
      Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']),
    )
  }
  return {}
}

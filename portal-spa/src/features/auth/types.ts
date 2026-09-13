/** Utilisateur authentifié tel qu'exposé par GET /api/user (CurrentUserController). */
export interface AuthUser {
  id: number
  first_name: string
  last_name: string
  email: string
  theme: 'light' | 'dark' | null
  two_factor_enabled: boolean
  roles: string[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

/** Corps de POST /reset-password (Fortify). */
export interface ResetPasswordInput {
  token: string
  email: string
  password: string
  password_confirmation: string
}

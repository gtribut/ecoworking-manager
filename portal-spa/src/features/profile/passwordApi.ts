import { http } from '@/lib/http'

export interface UpdatePasswordInput {
  current_password: string
  password: string
  password_confirmation: string
}

/**
 * Changement de mot de passe (Fortify, `PUT /user/password`, PRD §3.4.5).
 * Ré-authentification par le mot de passe actuel obligatoire côté back.
 */
export async function updatePassword(input: UpdatePasswordInput): Promise<void> {
  await http.put('/user/password', input)
}

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Alert } from './alert'

describe('Alert', () => {
  it('annonce les erreurs en role="alert" (restitution immédiate)', () => {
    render(<Alert variant="error">Identifiants invalides.</Alert>)

    expect(screen.getByRole('alert')).toHaveTextContent('Identifiants invalides.')
  })

  it('annonce les variantes non bloquantes en role="status"', () => {
    render(<Alert variant="success">Mot de passe modifié.</Alert>)

    expect(screen.getByRole('status')).toHaveTextContent('Mot de passe modifié.')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('laisse l’appelant forcer un role', () => {
    render(
      <Alert variant="info" role="note">
        Information
      </Alert>,
    )

    expect(screen.getByRole('note')).toBeVisible()
  })
})

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Input } from './Input'

describe('Input', () => {
  it('sans erreur : pas de aria-invalid ni de message', () => {
    render(<Input id="email" aria-label="Email" />)
    const input = screen.getByLabelText('Email')
    expect(input).not.toHaveAttribute('aria-invalid', 'true')
    expect(input).not.toHaveAttribute('aria-describedby')
  })

  it('avec une erreur : aria-invalid, message relié via aria-describedby', () => {
    render(<Input id="email" aria-label="Email" error="Email invalide." />)
    const input = screen.getByLabelText('Email')

    expect(input).toHaveAttribute('aria-invalid', 'true')
    const message = screen.getByText('Email invalide.')
    expect(input.getAttribute('aria-describedby')).toContain(message.id)
  })

  it('combine describedBy (aide) et l’erreur générée', () => {
    render(
      <Input
        id="password"
        aria-label="Mot de passe"
        describedBy="password-help"
        error="Trop court."
      />,
    )
    const input = screen.getByLabelText('Mot de passe')
    const describedBy = input.getAttribute('aria-describedby') ?? ''

    expect(describedBy).toContain('password-help')
    expect(describedBy).toContain('password-error')
  })
})

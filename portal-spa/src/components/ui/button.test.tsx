import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Button } from './button'

describe('Button', () => {
  it('vaut type="button" par défaut : posé dans un formulaire, il ne le soumet pas', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event: React.FormEvent) => event.preventDefault())

    render(
      <form onSubmit={onSubmit}>
        <Button>Réinitialiser</Button>
      </form>,
    )

    const button = screen.getByRole('button', { name: 'Réinitialiser' })
    expect(button).toHaveAttribute('type', 'button')

    await user.click(button)
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('respecte un type explicite', () => {
    render(
      <form>
        <Button type="submit">Enregistrer</Button>
      </form>,
    )

    expect(screen.getByRole('button', { name: 'Enregistrer' })).toHaveAttribute('type', 'submit')
  })

  it('en mode asChild, rend l’élément fourni sans lui imposer de type', () => {
    render(
      <Button asChild>
        <a href="/factures">Mes factures</a>
      </Button>,
    )

    const link = screen.getByRole('link', { name: 'Mes factures' })
    expect(link).not.toHaveAttribute('type')
  })
})

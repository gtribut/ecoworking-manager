import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { describe, expect, it, vi } from 'vitest'
import { QueryError } from './QueryError'

describe('QueryError', () => {
  it('affiche le message fourni et déclenche le réessai au clic', async () => {
    const user = userEvent.setup()
    const onRetry = vi.fn()

    render(<QueryError message="Impossible de charger vos réservations." onRetry={onRetry} />)

    expect(screen.getByText('Impossible de charger vos réservations.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Réessayer' }))

    expect(onRetry).toHaveBeenCalledOnce()
  })

  it("n'affiche aucun bouton de réessai si onRetry est omis", () => {
    render(<QueryError message="Erreur." />)
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument()
  })

  it('affiche « Accès refusé » pour une erreur 403 plutôt que le message serveur brut', () => {
    const error = new AxiosError('Forbidden', '403', undefined, undefined, {
      status: 403,
      data: { message: 'Vous n’avez pas la permission edit-anything.' },
      statusText: 'Forbidden',
      headers: {},
      // biome-ignore lint/suspicious/noExplicitAny: config Axios minimal pour le test
      config: {} as any,
    })

    render(<QueryError error={error} />)

    expect(screen.getByText('Accès refusé.')).toBeInTheDocument()
    expect(
      screen.queryByText('Vous n’avez pas la permission edit-anything.'),
    ).not.toBeInTheDocument()
  })
})

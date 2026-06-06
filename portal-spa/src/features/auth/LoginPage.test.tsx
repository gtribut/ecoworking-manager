import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { LoginPage } from './LoginPage'

// L'AuthProvider interroge /api/user au montage ; non authentifié au départ.
function withUnauthenticated() {
  server.use(http.get('/api/user', () => new HttpResponse(null, { status: 401 })))
  server.use(http.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })))
}

describe('LoginPage', () => {
  it('valide les champs requis côté client', async () => {
    const user = userEvent.setup()
    withUnauthenticated()

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    expect(await screen.findByText('L’email est requis.')).toBeInTheDocument()
    expect(screen.getByText('Le mot de passe est requis.')).toBeInTheDocument()
  })

  it('affiche une erreur quand les identifiants sont refusés (422)', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    server.use(
      http.post('/login', () =>
        HttpResponse.json({ message: 'Identifiants incorrects.' }, { status: 422 }),
      ),
    )

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.type(screen.getByLabelText('Email'), 'a@b.fr')
    await user.type(screen.getByLabelText('Mot de passe'), 'secret')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    expect(await screen.findByText('Identifiants incorrects.')).toBeInTheDocument()
  })

  it('bascule sur le défi 2FA quand Fortify le demande', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    server.use(http.post('/login', () => HttpResponse.json({ two_factor: true })))

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.type(screen.getByLabelText('Email'), 'a@b.fr')
    await user.type(screen.getByLabelText('Mot de passe'), 'secret')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    expect(await screen.findByLabelText('Code de vérification')).toBeInTheDocument()
  })
})

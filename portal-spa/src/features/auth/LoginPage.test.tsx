import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
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

  it('permet d’utiliser un code de récupération 2FA', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    const challengeSpy = vi.fn()
    server.use(
      http.post('/login', () => HttpResponse.json({ two_factor: true })),
      http.post('/two-factor-challenge', async ({ request }) => {
        challengeSpy(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    )

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.type(screen.getByLabelText('Email'), 'a@b.fr')
    await user.type(screen.getByLabelText('Mot de passe'), 'secret')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    await user.click(
      await screen.findByRole('button', { name: /utiliser un code de récupération/i }),
    )
    await user.type(screen.getByLabelText('Code de récupération'), 'abcdef-123456')
    await user.click(screen.getByRole('button', { name: /vérifier/i }))

    await waitFor(() =>
      expect(challengeSpy).toHaveBeenCalledWith({ recovery_code: 'abcdef-123456' }),
    )
  })

  it('propose un retour du défi 2FA vers le formulaire de connexion', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    server.use(http.post('/login', () => HttpResponse.json({ two_factor: true })))

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.type(screen.getByLabelText('Email'), 'a@b.fr')
    await user.type(screen.getByLabelText('Mot de passe'), 'secret')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    await user.click(await screen.findByRole('button', { name: /retour à la connexion/i }))

    expect(await screen.findByLabelText('Mot de passe')).toBeInTheDocument()
  })

  it('envoie la demande de magic link et affiche la confirmation générique', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    const magicLinkSpy = vi.fn()
    server.use(
      http.post('/magic-link', async ({ request }) => {
        magicLinkSpy(await request.json())
        return HttpResponse.json({ message: 'ok' })
      }),
    )

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.click(
      screen.getByRole('button', { name: /recevoir un lien de connexion par email/i }),
    )
    await user.type(screen.getByLabelText('Email'), 'membre@ecoworking.fr')
    await user.click(screen.getByRole('button', { name: /recevoir le lien de connexion/i }))

    // Message volontairement générique (anti-énumération) + annonce screen reader.
    const confirmation = await screen.findByRole('status')
    expect(confirmation).toHaveTextContent(/si un compte correspond à cette adresse/i)
    expect(magicLinkSpy).toHaveBeenCalledWith({ email: 'membre@ecoworking.fr' })
  })

  it('valide l’email requis du formulaire magic link sans appeler l’API', async () => {
    const user = userEvent.setup()
    withUnauthenticated()

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.click(
      screen.getByRole('button', { name: /recevoir un lien de connexion par email/i }),
    )
    await user.click(screen.getByRole('button', { name: /recevoir le lien de connexion/i }))

    expect(await screen.findByText('L’email est requis.')).toBeInTheDocument()
  })

  it('affiche l’erreur générique au retour d’un lien refusé (?magic_link=invalid)', async () => {
    withUnauthenticated()

    renderWithProviders(<LoginPage />, { withAuth: true, route: '/login?magic_link=invalid' })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(/invalide, déjà utilisé ou expiré/i)
    // Le formulaire de connexion reste disponible.
    expect(screen.getByLabelText('Mot de passe')).toBeInTheDocument()
  })

  it('envoie remember quand « Se souvenir de moi » est cochée', async () => {
    const user = userEvent.setup()
    withUnauthenticated()
    const loginSpy = vi.fn()
    server.use(
      http.post('/login', async ({ request }) => {
        loginSpy(await request.json())
        return HttpResponse.json({})
      }),
    )

    renderWithProviders(<LoginPage />, { withAuth: true })

    await user.type(screen.getByLabelText('Email'), 'a@b.fr')
    await user.type(screen.getByLabelText('Mot de passe'), 'secret')
    await user.click(screen.getByLabelText(/se souvenir de moi/i))
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    await waitFor(() =>
      expect(loginSpy).toHaveBeenCalledWith(expect.objectContaining({ remember: true })),
    )
  })
})

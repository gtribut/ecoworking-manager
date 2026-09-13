import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { ResetPasswordPage } from './ResetPasswordPage'

function renderAt(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
      <Route path="/login" element={<p>Page de connexion</p>} />
    </Routes>,
    { route },
  )
}

describe('ResetPasswordPage (R-03, PRD §3.2)', () => {
  it('pré-remplit l’email depuis le lien et refuse une confirmation différente', async () => {
    const user = userEvent.setup()
    renderAt('/reset-password/tok-123?email=claire%40example.test')

    expect(screen.getByLabelText('Email')).toHaveValue('claire@example.test')

    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'motdepasse-solide')
    await user.type(screen.getByLabelText('Confirmation'), 'autre-chose')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    expect(
      await screen.findByText('Les deux mots de passe ne correspondent pas.'),
    ).toBeInTheDocument()
  })

  it('poste le jeton + email + mots de passe puis renvoie au login avec confirmation', async () => {
    const user = userEvent.setup()
    let body: Record<string, unknown> | null = null
    server.use(
      http.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
      http.post('/reset-password', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ message: 'ok' })
      }),
    )
    renderAt('/reset-password/tok-123?email=claire%40example.test')

    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'motdepasse-solide')
    await user.type(screen.getByLabelText('Confirmation'), 'motdepasse-solide')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    expect(await screen.findByText('Page de connexion')).toBeInTheDocument()
    await waitFor(() =>
      expect(body).toEqual({
        token: 'tok-123',
        email: 'claire@example.test',
        password: 'motdepasse-solide',
        password_confirmation: 'motdepasse-solide',
      }),
    )
  })

  it('affiche l’erreur Laravel (jeton invalide, 422) sans quitter la page', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
      http.post('/reset-password', () =>
        HttpResponse.json(
          { message: 'Ce jeton de réinitialisation du mot de passe n’est pas valide.' },
          { status: 422 },
        ),
      ),
    )
    renderAt('/reset-password/perime?email=claire%40example.test')

    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'motdepasse-solide')
    await user.type(screen.getByLabelText('Confirmation'), 'motdepasse-solide')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    expect(
      await screen.findByText('Ce jeton de réinitialisation du mot de passe n’est pas valide.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Nouveau mot de passe' })).toBeInTheDocument()
  })
})

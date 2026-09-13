import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { PasswordSection } from './PasswordSection'

describe('PasswordSection (PRD §3.4.2 / §3.4.5)', () => {
  it('change le mot de passe et vide les champs après succès', async () => {
    const user = userEvent.setup()
    server.use(http.put('/user/password', () => new HttpResponse(null, { status: 200 })))

    renderWithProviders(<PasswordSection />)

    await user.type(screen.getByLabelText('Mot de passe actuel'), 'ancien-mot-de-passe')
    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'nouveau-mot-de-passe-2026')
    await user.type(
      screen.getByLabelText('Confirmer le nouveau mot de passe'),
      'nouveau-mot-de-passe-2026',
    )
    await user.click(screen.getByRole('button', { name: 'Modifier mon mot de passe' }))

    await waitFor(() => expect(screen.getByText('Mot de passe modifié.')).toBeInTheDocument())
    expect(screen.getByLabelText('Mot de passe actuel')).toHaveValue('')
    expect(screen.getByLabelText('Nouveau mot de passe')).toHaveValue('')
    expect(screen.getByLabelText('Confirmer le nouveau mot de passe')).toHaveValue('')
  })

  it('affiche l’erreur 422 sous le champ mot de passe actuel', async () => {
    const user = userEvent.setup()
    server.use(
      http.put('/user/password', () =>
        HttpResponse.json(
          {
            message: 'The given data was invalid.',
            errors: { current_password: ['Mot de passe incorrect.'] },
          },
          { status: 422 },
        ),
      ),
    )

    renderWithProviders(<PasswordSection />)

    await user.type(screen.getByLabelText('Mot de passe actuel'), 'mauvais')
    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'nouveau-mot-de-passe-2026')
    await user.type(
      screen.getByLabelText('Confirmer le nouveau mot de passe'),
      'nouveau-mot-de-passe-2026',
    )
    await user.click(screen.getByRole('button', { name: 'Modifier mon mot de passe' }))

    expect(await screen.findByText('Mot de passe incorrect.')).toBeInTheDocument()
    expect(screen.getByLabelText('Mot de passe actuel')).toHaveAttribute('aria-invalid', 'true')
  })

  it('bloque la soumission côté client si la confirmation diffère (aucun appel réseau)', async () => {
    const user = userEvent.setup()
    let calls = 0
    server.use(
      http.put('/user/password', () => {
        calls += 1
        return new HttpResponse(null, { status: 200 })
      }),
    )

    renderWithProviders(<PasswordSection />)

    await user.type(screen.getByLabelText('Mot de passe actuel'), 'ancien-mot-de-passe')
    await user.type(screen.getByLabelText('Nouveau mot de passe'), 'nouveau-mot-de-passe-2026')
    await user.type(screen.getByLabelText('Confirmer le nouveau mot de passe'), 'autre-chose')
    await user.click(screen.getByRole('button', { name: 'Modifier mon mot de passe' }))

    expect(
      await screen.findByText('La confirmation ne correspond pas au nouveau mot de passe.'),
    ).toBeInTheDocument()
    expect(calls).toBe(0)
  })
})

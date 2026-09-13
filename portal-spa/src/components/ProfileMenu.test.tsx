import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { makeAuthUser, renderWithProviders } from '@/test/utils'
import { ProfileMenu } from './ProfileMenu'

function mockUser(overrides: Parameters<typeof makeAuthUser>[0] = {}) {
  server.use(http.get('/api/user', () => HttpResponse.json(makeAuthUser(overrides))))
}

describe('ProfileMenu', () => {
  it('ouvre le menu au clic et affiche les entrées attendues', async () => {
    const user = userEvent.setup()
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: /Alex Martin/ })
    await user.click(trigger)

    expect(screen.getByRole('menu', { name: 'Menu profil' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: /Mon profil/ })).toBeInTheDocument()
    expect(screen.getByRole('menuitemradio', { name: 'Clair' })).toBeInTheDocument()
    expect(screen.getByRole('menuitemradio', { name: 'Sombre' })).toBeInTheDocument()
    expect(screen.getByRole('menuitemradio', { name: 'Système' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: /Déconnexion/ })).toBeInTheDocument()
  })

  it('navigue au clavier (flèches) et ferme avec Échap en rendant le focus au déclencheur', async () => {
    const user = userEvent.setup()
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: /Alex Martin/ })
    await user.click(trigger)

    const profileItem = screen.getByRole('menuitem', { name: /Mon profil/ })
    expect(profileItem).toHaveFocus()

    await user.keyboard('{ArrowDown}')
    expect(screen.getByRole('menuitemradio', { name: 'Clair' })).toHaveFocus()

    await user.keyboard('{ArrowDown}')
    expect(screen.getByRole('menuitemradio', { name: 'Sombre' })).toHaveFocus()

    await user.keyboard('{ArrowUp}')
    expect(screen.getByRole('menuitemradio', { name: 'Clair' })).toHaveFocus()

    await user.keyboard('{Escape}')
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()
    expect(trigger).toHaveFocus()
  })

  it('choisit un thème : applique la classe dark immédiatement et enregistre côté serveur', async () => {
    const user = userEvent.setup()
    mockUser()
    let patchedTheme: unknown
    server.use(
      http.patch('/api/profile', async ({ request }) => {
        const body = (await request.json()) as { theme?: unknown }
        patchedTheme = body.theme
        return HttpResponse.json({
          user: { ...makeAuthUser(), theme: body.theme },
          profile: null,
          company: null,
        })
      }),
    )
    document.documentElement.classList.remove('dark')

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /Alex Martin/ }))
    await user.click(screen.getByRole('menuitemradio', { name: 'Sombre' }))

    expect(document.documentElement.classList.contains('dark')).toBe(true)
    await waitFor(() => expect(patchedTheme).toBe('dark'))
  })

  it('se déconnecte depuis le menu', async () => {
    const user = userEvent.setup()
    mockUser()
    let loggedOut = false
    server.use(
      http.post('/logout', () => {
        loggedOut = true
        return HttpResponse.json({ message: 'ok' })
      }),
    )

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /Alex Martin/ }))
    await user.click(screen.getByRole('menuitem', { name: /Déconnexion/ }))

    await waitFor(() => expect(loggedOut).toBe(true))
  })
})

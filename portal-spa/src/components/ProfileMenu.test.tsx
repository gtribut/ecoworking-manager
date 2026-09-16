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

/*
 * Depuis C14 (U2), le menu profil est un `DropdownMenu` shadcn/ui (Radix) :
 * mêmes rôles ARIA qu'avant, mais le panneau est rendu dans un **portail** et
 * c'est Radix qui pilote le focus — ouverture à la souris = focus sur le
 * panneau (une flèche bas entre dans la liste), sélection d'une option =
 * fermeture, focus rendu au déclencheur.
 */
describe('ProfileMenu', () => {
  it('porte un nom accessible avec le nom du membre (review — bouton sans texte visible < 640px)', async () => {
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    expect(
      await screen.findByRole('button', { name: 'Menu profil de Alex Martin' }),
    ).toBeInTheDocument()
  })

  it('ouvre le menu au clic et affiche les entrées attendues', async () => {
    const user = userEvent.setup()
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: /Alex Martin/ })
    await user.click(trigger)

    expect(
      await screen.findByRole('menu', { name: /Menu profil de Alex Martin/ }),
    ).toBeInTheDocument()
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
    await screen.findByRole('menu', { name: /Menu profil de Alex Martin/ })

    await user.keyboard('{ArrowDown}')
    expect(screen.getByRole('menuitem', { name: /Mon profil/ })).toHaveFocus()

    await user.keyboard('{ArrowDown}')
    expect(screen.getByRole('menuitemradio', { name: 'Clair' })).toHaveFocus()

    await user.keyboard('{ArrowDown}')
    expect(screen.getByRole('menuitemradio', { name: 'Sombre' })).toHaveFocus()

    await user.keyboard('{ArrowUp}')
    expect(screen.getByRole('menuitemradio', { name: 'Clair' })).toHaveFocus()

    await user.keyboard('{Escape}')
    await waitFor(() => expect(screen.queryByRole('menu')).not.toBeInTheDocument())
    expect(trigger).toHaveFocus()
  })

  it('ouvre le menu au clavier (Entrée) en plaçant le focus sur la première entrée', async () => {
    const user = userEvent.setup()
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: /Alex Martin/ })
    trigger.focus()
    await user.keyboard('{Enter}')

    expect(await screen.findByRole('menuitem', { name: /Mon profil/ })).toHaveFocus()
  })

  it('ne perd jamais le focus sur Tab (review — focus retombé sur <body> avec le menu maison)', async () => {
    const user = userEvent.setup()
    mockUser()

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /Alex Martin/ }))
    const menu = await screen.findByRole('menu')
    await user.keyboard('{ArrowDown}')

    // Radix neutralise Tab tant que le menu est ouvert (piège de focus APG) :
    // le focus reste dans le panneau au lieu de retomber sur <body>.
    await user.keyboard('{Tab}')

    expect(document.activeElement).not.toBe(document.body)
    expect(menu.contains(document.activeElement)).toBe(true)
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
    await user.click(await screen.findByRole('menuitemradio', { name: 'Sombre' }))

    expect(document.documentElement.classList.contains('dark')).toBe(true)
    await waitFor(() => expect(patchedTheme).toBe('dark'))
  })

  it('annule le changement de thème si le serveur refuse (review — pas de rollback ni de message)', async () => {
    const user = userEvent.setup()
    mockUser()
    server.use(http.patch('/api/profile', () => new HttpResponse(null, { status: 500 })))
    document.documentElement.classList.remove('dark')

    renderWithProviders(<ProfileMenu />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: /Alex Martin/ })
    await user.click(trigger)
    // Sélectionner une option ferme le menu (comportement Radix).
    await user.click(await screen.findByRole('menuitemradio', { name: 'Sombre' }))

    // Appliqué en optimiste puis annulé (classe ET état du menu) dès que le
    // serveur répond en erreur (résolution MSW quasi immédiate ici : on
    // n'observe que l'état final, pas l'intermédiaire).
    // `makeAuthUser()` a `theme: null` (Système) : c'est la valeur de repli.
    await waitFor(() => expect(document.documentElement.classList.contains('dark')).toBe(false))
    expect(await screen.findByText(/Impossible d’enregistrer le thème/)).toBeInTheDocument()

    await user.click(trigger)
    await waitFor(() =>
      expect(screen.getByRole('menuitemradio', { name: 'Système' })).toHaveAttribute(
        'aria-checked',
        'true',
      ),
    )
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
    await user.click(await screen.findByRole('menuitem', { name: /Déconnexion/ }))

    await waitFor(() => expect(loggedOut).toBe(true))
  })
})

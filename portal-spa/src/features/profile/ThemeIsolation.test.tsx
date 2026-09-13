import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { ProfileMenu } from '@/components/ProfileMenu'
import { server } from '@/test/server'
import { MEMBER_PERMISSIONS, makeAuthUser, renderWithProviders } from '@/test/utils'
import { ProfilePage } from './ProfilePage'
import type { ProfilePayload } from './types'

const payload: ProfilePayload = {
  user: {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    notify_email: true,
    notify_in_app: true,
  },
  profile: {
    id: 10,
    status: 'active',
    job_title: 'Designer',
    bio: 'ancien',
    interests: null,
    linkedin_url: null,
    website_url: null,
    birth_date: null,
    photo: null,
    show_in_directory: true,
    newsletter_opt_in: false,
    arrival_date: null,
    desk: null,
  },
  company: null,
}

/**
 * Réplique le header + la page profil sous le même QueryClient — comme dans
 * l'app réelle (Layout + route). Reproduit le bug de review : changer le
 * thème depuis le menu déclenchait `useUpdateProfile`, qui écrivait dans le
 * cache `profileQueryKey` ; l'effet de `ProfilePage` réagissait en `reset()`
 * son formulaire, effaçant une saisie en cours.
 */
describe('Isolation thème / formulaire profil (review)', () => {
  it('changer le thème depuis le menu ne perd pas une saisie de bio en cours', async () => {
    const user = userEvent.setup()
    document.documentElement.classList.remove('dark')

    server.use(
      http.get('/api/user', () =>
        HttpResponse.json(makeAuthUser({ roles: ['resident'], permissions: MEMBER_PERMISSIONS })),
      ),
      http.get('/api/profile', () => HttpResponse.json(payload)),
      http.patch('/api/profile', async ({ request }) => {
        const body = (await request.json()) as { theme?: 'light' | 'dark' | null }
        return HttpResponse.json({
          ...payload,
          user: { ...payload.user, theme: body.theme ?? null },
        })
      }),
    )

    renderWithProviders(
      <>
        <ProfileMenu />
        <ProfilePage />
      </>,
      { withAuth: true },
    )

    const bio = await screen.findByLabelText('Présentation')
    await user.clear(bio)
    await user.type(bio, 'Brouillon en cours de rédaction')
    expect(bio).toHaveValue('Brouillon en cours de rédaction')

    await user.click(await screen.findByRole('button', { name: /Alex Martin/ }))
    await user.click(screen.getByRole('menuitemradio', { name: 'Sombre' }))

    await waitFor(() => expect(document.documentElement.classList.contains('dark')).toBe(true))

    // La saisie de bio doit survivre au changement de thème.
    expect(bio).toHaveValue('Brouillon en cours de rédaction')
  })
})

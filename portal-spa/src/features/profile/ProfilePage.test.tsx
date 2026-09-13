import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
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
    photo_path: null,
    show_in_directory: true,
    newsletter_opt_in: false,
    arrival_date: null,
    desk: null,
  },
  company: {
    id: 5,
    entity_type: 'company',
    name: 'Acme SCOP',
    legal_name: 'Acme SCOP',
    legal_form: 'SCOP',
    siret: '12345678901234',
    vat_number: null,
    billing_email: 'fact@acme.fr',
    address: {
      line1: '10 rue du Lac',
      line2: null,
      postal_code: '69003',
      city: 'Lyon',
      country: 'FR',
    },
  },
}

/** L'AuthProvider (TwoFactorSection) interroge /api/user : membre sans 2FA. */
function withUser() {
  server.use(
    http.get('/api/user', () =>
      HttpResponse.json(makeAuthUser({ roles: ['resident'], permissions: MEMBER_PERMISSIONS })),
    ),
  )
}

describe('ProfilePage', () => {
  it('affiche les infos perso et l’entité (lecture seule)', async () => {
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    expect(await screen.findByDisplayValue('Designer')).toBeInTheDocument()
    expect(screen.getByDisplayValue('alex@ex.fr')).toBeDisabled()
    expect(screen.getByText('Acme SCOP')).toBeInTheDocument()
  })

  it('enregistre une modification de profil', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/profile', () => HttpResponse.json(payload)),
      http.patch('/api/profile', async ({ request }) => {
        const body = (await request.json()) as { bio?: string }
        return HttpResponse.json({
          ...payload,
          profile: { ...payload.profile, bio: body.bio ?? null } as ProfilePayload['profile'],
        })
      }),
    )

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    const bio = await screen.findByLabelText('Présentation')
    await user.clear(bio)
    await user.type(bio, 'Nouvelle bio')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => expect(screen.getByText('Profil mis à jour.')).toBeInTheDocument())
  })

  it('bloque la soumission sur une URL invalide', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    const linkedin = await screen.findByLabelText('LinkedIn')
    await user.type(linkedin, 'pas-une-url')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    expect(await screen.findByText('URL invalide.')).toBeInTheDocument()
  })
})

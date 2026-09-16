import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { toast } from 'sonner'
import { afterEach, describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { MEMBER_PERMISSIONS, makeAuthUser, renderWithProviders } from '@/test/utils'
import { ProfilePage } from './ProfilePage'
import type { ProfilePayload } from './types'

// Le store de `sonner` est un singleton hors React : un toast déclenché par
// un test (ex. `onInvalid`) survit à son démontage et peut être « dédupliqué »
// (message identique) au lieu d'apparaître à nouveau dans le test suivant.
// Sans ce nettoyage, « bloque la soumission sur une URL invalide » (qui
// déclenche désormais le même toast d'erreur générique) fait échouer le test
// B-3 juste après, de façon non déterministe selon l'ordre d'exécution.
afterEach(() => {
  toast.dismiss()
})

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
  it('affiche les infos perso (onglet Profil, actif par défaut)', async () => {
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    expect(await screen.findByDisplayValue('Designer')).toBeInTheDocument()
    expect(screen.getByDisplayValue('alex@ex.fr')).toBeDisabled()
  })

  it('affiche l’entité dans l’onglet Entreprise après clic (masquée par défaut)', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true, route: '/profile' })

    await screen.findByDisplayValue('Designer')
    expect(screen.queryByText('Acme SCOP')).not.toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Entreprise' }))

    expect(await screen.findByText('Acme SCOP')).toBeInTheDocument()
  })

  it('ouvre directement l’onglet demandé par ?tab= (lien depuis ailleurs)', async () => {
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true, route: '/profile?tab=compte' })

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Mot de passe' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Compte', selected: true })).toBeInTheDocument()
    // Le contenu de l'onglet Profil (démonté) n'est plus dans le DOM.
    expect(screen.queryByLabelText('Présentation')).not.toBeInTheDocument()
  })

  it('propose la section photo de profil avec l’avatar initiales (PRD §3.4.2)', async () => {
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Photo de profil' }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Choisir une photo')).toBeInTheDocument()
    expect(screen.getByText('AM')).toBeInTheDocument()
  })

  it('affiche l’entité complète sans les coordonnées bancaires (PRD §3.4.3)', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/profile', () =>
        HttpResponse.json({
          ...payload,
          company: {
            ...payload.company,
            payment_method: 'transfer',
            payment_method_label: 'Virement',
            iban_last4: '9876',
          },
        }),
      ),
    )

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    await screen.findByDisplayValue('Designer')
    await user.click(screen.getByRole('tab', { name: 'Entreprise' }))

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Mon entreprise' }),
    ).toBeInTheDocument()
    // Adresse complète : le pays est rendu (écart §3.4 « adresse tronquée »).
    expect(screen.getByText('France')).toBeInTheDocument()
    // Mode de paiement et IBAN-4 restent au module administratif (§3.6.4).
    expect(screen.queryByText('Virement')).not.toBeInTheDocument()
    expect(screen.queryByText('•••• 9876')).not.toBeInTheDocument()
  })

  it('explique l’absence d’entité juridique rattachée', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/profile', () => HttpResponse.json({ ...payload, company: null })))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    await screen.findByDisplayValue('Designer')
    await user.click(screen.getByRole('tab', { name: 'Entreprise' }))

    expect(await screen.findByText(/Aucune entité juridique/)).toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { level: 2, name: 'Mon entreprise' }),
    ).not.toBeInTheDocument()
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

  it('bascule sur l’onglet Profil et alerte quand une erreur y survient en soumettant depuis Préférences (B-3)', async () => {
    const user = userEvent.setup()
    server.use(http.get('/api/profile', () => HttpResponse.json(payload)))

    withUser()
    renderWithProviders(<ProfilePage />, { withAuth: true })

    // Erreur saisie dans l'onglet Profil (actif par défaut)…
    const linkedin = await screen.findByLabelText('LinkedIn')
    await user.type(linkedin, 'pas-une-url')

    // …puis on bascule sur Préférences (Radix démonte l'onglet Profil : le
    // champ en erreur n'est plus dans le DOM) et on soumet depuis là.
    await user.click(screen.getByRole('tab', { name: 'Préférences' }))
    await waitFor(() => expect(screen.queryByLabelText('LinkedIn')).not.toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    // L'échec ne doit pas être silencieux (WCAG 3.3.1) : message visible…
    expect(await screen.findByText('Certains champs sont invalides.')).toBeInTheDocument()
    // …et retour automatique sur l'onglet qui porte le champ en erreur —
    // `setActiveTab` passe par `setSearchParams` (react-router), pas garanti
    // synchrone avec le rendu du toast : `waitFor` plutôt qu'une assertion
    // immédiate.
    await waitFor(() =>
      expect(screen.getByRole('tab', { name: 'Profil', selected: true })).toBeInTheDocument(),
    )
    expect(await screen.findByText('URL invalide.')).toBeInTheDocument()
    // Le focus atterrit via une petite boucle de nouvelles tentatives
    // (`requestAnimationFrame`) tant que Radix Tabs n'a pas fini de monter
    // le contenu de l'onglet — d'où le `waitFor` plutôt qu'une assertion
    // synchrone.
    await waitFor(() => expect(screen.getByLabelText('LinkedIn')).toHaveFocus())
  })
})

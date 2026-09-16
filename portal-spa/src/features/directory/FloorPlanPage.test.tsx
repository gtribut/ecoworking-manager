import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { FloorPlanPage } from './FloorPlanPage'
import type { FloorPlan, PlanDesk, PlanOccupantVisible } from './types'

const PHOTO = {
  sm: '/api/users/7/photo/80',
  md: '/api/users/7/photo/200',
  lg: '/api/users/7/photo/400',
}

function visibleOccupant(photo: PlanOccupantVisible['photo'] = null): PlanOccupantVisible {
  return {
    visible: true,
    member_profile_id: 10,
    first_name: 'Marie',
    last_name: 'Durand',
    photo,
    job_title: 'Designer',
    bio: null,
    interests: null,
    linkedin_url: null,
    website_url: null,
    company: 'Acme Studio',
  }
}

function makeDesk(overrides: Partial<PlanDesk> = {}): PlanDesk {
  return {
    resource_id: 1,
    svg_desk_id: 'desk-1',
    name: 'Bureau 1',
    floor: 1,
    assignment: 'assigned_resident',
    is_own: false,
    status: 'present',
    occupant: visibleOccupant(),
    ...overrides,
  }
}

const defaultPlan: FloorPlan = {
  date: '2026-07-01',
  is_working_day: true,
  desks: [
    makeDesk(),
    makeDesk({
      resource_id: 2,
      svg_desk_id: 'desk-2',
      name: 'Bureau 2',
      occupant: { visible: false },
    }),
    makeDesk({
      resource_id: 30,
      svg_desk_id: 'desk-30',
      name: 'Bureau 30',
      floor: 2,
      assignment: 'unassigned',
      status: 'free',
      occupant: null,
    }),
  ],
}

function mockPlan(plan: FloorPlan = defaultPlan) {
  server.use(http.get('/api/directory/floor-plan', () => HttpResponse.json(plan)))
}

/** Le SVG est décoré en useEffect : attendre la liste avant d'inspecter le DOM. */
async function renderPlan(plan: FloorPlan = defaultPlan) {
  mockPlan(plan)
  renderWithProviders(<FloorPlanPage />)
  await screen.findByText('Occupation en liste')
}

describe('FloorPlanPage', () => {
  it('colore les bureaux du SVG via data-desk et pose des labels accessibles', async () => {
    await renderPlan()

    await waitFor(() => {
      expect(document.querySelector('#desk-1')).toHaveAttribute('data-status', 'present')
    })
    const desk1 = document.querySelector('#desk-1')
    expect(desk1).toHaveAttribute('role', 'button')
    expect(desk1).toHaveAttribute('tabindex', '0')
    expect(desk1?.getAttribute('aria-label')).toBe(
      'Bureau 1 — Marie Durand (Acme Studio), présent(e)',
    )
    // Opt-out : aucune identité, mention anonyme (PRD §3.7.5).
    expect(document.querySelector('#desk-2')?.getAttribute('aria-label')).toBe(
      'Bureau 2 — Coworker (souhaite rester discret), présent(e)',
    )
  })

  it('fournit l’alternative accessible : liste texte de l’occupation par étage', async () => {
    await renderPlan()

    expect(screen.getByRole('heading', { name: 'Étage 1' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Étage 2' })).toBeInTheDocument()
    expect(
      screen.getByText('Bureau 1 — Marie Durand (Acme Studio), présent(e)'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Bureau 2 — Coworker (souhaite rester discret), présent(e)'),
    ).toBeInTheDocument()
    expect(screen.getByText('Bureau 30 — libre')).toBeInTheDocument()
  })

  it('navigue entre les étages (boutons pressés + zones SVG affichées)', async () => {
    await renderPlan()

    const floor1 = screen.getByRole('button', { name: 'Étage 1' })
    const floor2 = screen.getByRole('button', { name: 'Étage 2' })
    expect(floor1).toHaveAttribute('aria-pressed', 'true')

    await waitFor(() => {
      expect((document.querySelector('#etage-2') as SVGGElement | null)?.style.display).toBe('none')
    })

    const user = userEvent.setup()
    await user.click(floor2)

    expect(floor2).toHaveAttribute('aria-pressed', 'true')
    await waitFor(() => {
      expect((document.querySelector('#etage-1') as SVGGElement | null)?.style.display).toBe('none')
    })
    expect((document.querySelector('#etage-2') as SVGGElement | null)?.style.display).toBe('')
  })

  it('ouvre la fiche du bureau au clic et à l’Entrée clavier, avec fermeture', async () => {
    await renderPlan()
    await waitFor(() => {
      expect(document.querySelector('#desk-1')).toHaveAttribute('role', 'button')
    })

    const user = userEvent.setup()
    const desk1 = document.querySelector('#desk-1') as Element
    await user.click(desk1)

    // Panneau de détail rendu en `Sheet` (portail Radix) : le titre reste un
    // `<h2>` accessible, cherché au niveau du document comme le reste du DOM.
    const panel = await screen.findByRole('heading', { name: /Bureau 1/ })
    expect(panel).toBeInTheDocument()
    expect(screen.getByText('Statut : présent(e)')).toBeInTheDocument()
    expect(screen.getByText('Marie Durand')).toBeInTheDocument()
    expect(screen.getByText('Acme Studio')).toBeInTheDocument()

    // Bouton de fermeture par défaut du `Sheet` shadcn (lot U4b) — plus de
    // bouton « Fermer le détail du bureau » maison.
    await user.click(screen.getByRole('button', { name: 'Fermer cette fenêtre' }))
    expect(screen.queryByText('Statut : présent(e)')).not.toBeInTheDocument()
  })

  it('ferme le panneau à l’Échap (fermeture Radix, sans passer par le bouton)', async () => {
    await renderPlan()
    await waitFor(() => {
      expect(document.querySelector('#desk-1')).toHaveAttribute('role', 'button')
    })

    const user = userEvent.setup()
    const desk1 = document.querySelector('#desk-1') as HTMLElement
    desk1.focus()
    await user.keyboard('{Enter}')

    await screen.findByRole('heading', { name: /Bureau 1/ })
    await user.keyboard('{Escape}')

    expect(screen.queryByRole('heading', { name: /Bureau 1/ })).not.toBeInTheDocument()
  })

  it('propose « Gérer mes absences » sur SON propre bureau', async () => {
    await renderPlan({
      ...defaultPlan,
      desks: [makeDesk({ is_own: true })],
    })
    await waitFor(() => {
      expect(document.querySelector('#desk-1')).toHaveAttribute('role', 'button')
    })

    const user = userEvent.setup()
    await user.click(document.querySelector('#desk-1') as Element)

    const link = await screen.findByRole('link', { name: 'Gérer mes absences' })
    expect(link).toHaveAttribute('href', '/presence')
  })

  it('pose la photo du résident sur son bureau, en décoration (PRD §3.7.3)', async () => {
    await renderPlan({
      ...defaultPlan,
      desks: [makeDesk({ occupant: visibleOccupant(PHOTO) })],
    })

    await waitFor(() => {
      const image = document.querySelector('#desk-1 [data-desk-photo]')
      expect(image).not.toBeNull()
      // Rendu 80 px (le plus petit) et hors arbre d'accessibilité : l'info est
      // déjà portée par l'aria-label du bloc et l'alternative texte.
      expect(image).toHaveAttribute('href', PHOTO.sm)
      expect(image).toHaveAttribute('aria-hidden', 'true')
    })
  })

  it('grise la photo d’un résident absent et n’en pose aucune sans opt-in', async () => {
    await renderPlan({
      ...defaultPlan,
      desks: [
        makeDesk({
          status: 'absent',
          occupant: visibleOccupant(PHOTO),
        }),
        makeDesk({
          resource_id: 2,
          svg_desk_id: 'desk-2',
          name: 'Bureau 2',
          occupant: { visible: false },
        }),
      ],
    })

    await waitFor(() => {
      expect(document.querySelector('#desk-1 [data-desk-photo]')).toHaveAttribute('opacity', '0.45')
    })
    expect(document.querySelector('#desk-2 [data-desk-photo]')).toBeNull()
  })

  it('signale un jour non ouvré', async () => {
    await renderPlan({ ...defaultPlan, is_working_day: false })

    expect(
      screen.getByText('Jour non ouvré : les bureaux attitrés sont affichés absents.'),
    ).toBeInTheDocument()
  })
})

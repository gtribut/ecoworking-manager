import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { MEMBER_PERMISSIONS, makeAuthUser, renderWithProviders } from '@/test/utils'
import { PresencePage } from './PresencePage'
import type { Absence } from './types'

/** Résident doté d'un bureau attitré : seul profil autorisé sur cette page. */
function resident(): AuthUser {
  return makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS })
}

const desk = { id: 2, name: 'Bureau 12', floor: 2, svg_desk_id: 'desk-12' }

function absence(overrides: Partial<Absence> = {}): Absence {
  return {
    id: 5,
    date_start: '2026-06-20',
    date_end: '2026-06-22',
    period: 'full_day',
    recurrence_type: 'none',
    recurrence_day_of_week: null,
    notes: null,
    can_edit: true,
    can_delete: true,
    ...overrides,
  }
}

/** Réponse `/api/presence` ; `all=1` renvoie l'historique. */
function presenceHandlers(upcoming: Absence[], history: Absence[] = upcoming) {
  return [
    http.get('/api/user', () => HttpResponse.json(resident())),
    http.get('/api/presence', ({ request }) => {
      const all = new URL(request.url).searchParams.get('all')
      return HttpResponse.json({
        desk,
        present_days: [],
        absences: all === '1' ? history : upcoming,
      })
    }),
  ]
}

describe('PresencePage', () => {
  it('affiche le bureau attitré et liste les absences à venir', async () => {
    server.use(...presenceHandlers([absence()]))

    renderWithProviders(<PresencePage />, { withAuth: true })

    expect(await screen.findByText(/Bureau 12/)).toBeInTheDocument()
    expect(screen.getByText(/étage 2/)).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Mes absences à venir' })).toBeInTheDocument()
    expect(screen.getByText('du 20/06/2026 au 22/06/2026')).toBeInTheDocument()
  })

  it('révèle le formulaire au clic sur « Marquer une absence »', async () => {
    const user = userEvent.setup()
    server.use(...presenceHandlers([]))

    renderWithProviders(<PresencePage />, { withAuth: true })

    const trigger = await screen.findByRole('button', { name: 'Marquer une absence' })
    expect(trigger).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByLabelText('Date de début')).not.toBeInTheDocument()

    await user.click(trigger)

    expect(trigger).toHaveAttribute('aria-expanded', 'true')
    const dateStart = await screen.findByLabelText('Date de début')
    // Le focus entre dans le formulaire révélé (navigation clavier).
    await waitFor(() => expect(dateStart).toHaveFocus())
  })

  it('déclare une absence récurrente bornée avec une note', async () => {
    const user = userEvent.setup()
    const createSpy = vi.fn()
    server.use(
      ...presenceHandlers([]),
      http.post('/api/absences', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json({ data: absence() }, { status: 201 })
      }),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: 'Marquer une absence' }))
    await user.type(await screen.findByLabelText('Date de début'), '2026-06-01')
    await user.selectOptions(screen.getByLabelText('Récurrence'), 'weekly')
    await user.selectOptions(screen.getByLabelText('Jour de la semaine'), '5')
    await user.type(screen.getByLabelText('Date de fin (optionnel)'), '2026-09-30')
    await user.type(
      screen.getByLabelText('Note (visible par Ecoworking uniquement)'),
      'Télétravail',
    )
    await user.click(screen.getByRole('button', { name: 'Enregistrer l’absence' }))

    await waitFor(() => expect(screen.getByText('Absence enregistrée.')).toBeInTheDocument())
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({
      date_start: '2026-06-01',
      date_end: '2026-09-30',
      recurrence_type: 'weekly',
      recurrence_day_of_week: 5,
      notes: 'Télétravail',
    })
  })

  it('exige un jour de la semaine pour une absence hebdomadaire', async () => {
    const user = userEvent.setup()
    server.use(...presenceHandlers([]))

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: 'Marquer une absence' }))
    await user.type(await screen.findByLabelText('Date de début'), '2026-06-25')
    await user.selectOptions(screen.getByLabelText('Récurrence'), 'weekly')
    await user.click(screen.getByRole('button', { name: 'Enregistrer l’absence' }))

    expect(await screen.findByText(/choisissez un jour de la semaine/i)).toBeInTheDocument()
  })

  it('rattache une erreur 422 au champ concerné (aria-describedby)', async () => {
    const user = userEvent.setup()
    server.use(
      ...presenceHandlers([]),
      http.post('/api/absences', () =>
        HttpResponse.json(
          {
            message: 'Les données sont invalides.',
            errors: { notes: ['La note ne peut pas dépasser 255 caractères.'] },
          },
          { status: 422 },
        ),
      ),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: 'Marquer une absence' }))
    await user.type(await screen.findByLabelText('Date de début'), '2026-06-25')
    await user.click(screen.getByRole('button', { name: 'Enregistrer l’absence' }))

    const field = await screen.findByLabelText('Note (visible par Ecoworking uniquement)')
    await waitFor(() => expect(field).toHaveAttribute('aria-describedby', 'notes-error'))
    expect(screen.getByText('La note ne peut pas dépasser 255 caractères.')).toBeInTheDocument()
  })

  it('modifie une absence via un formulaire pré-rempli (PATCH)', async () => {
    const user = userEvent.setup()
    const updateSpy = vi.fn()
    server.use(
      ...presenceHandlers([absence({ notes: 'Congés' })]),
      http.patch('/api/absences/5', async ({ request }) => {
        updateSpy(await request.json())
        return HttpResponse.json({ data: absence() })
      }),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /^Modifier/ }))

    expect(await screen.findByLabelText('Date de début')).toHaveValue('2026-06-20')
    expect(screen.getByLabelText('Note (visible par Ecoworking uniquement)')).toHaveValue('Congés')

    await user.clear(screen.getByLabelText('Date de fin (optionnel)'))
    await user.type(screen.getByLabelText('Date de fin (optionnel)'), '2026-06-25')
    await user.click(screen.getByRole('button', { name: 'Enregistrer les modifications' }))

    await waitFor(() => expect(screen.getByText('Absence modifiée.')).toBeInTheDocument())
    expect(updateSpy.mock.calls[0]?.[0]).toMatchObject({
      date_start: '2026-06-20',
      date_end: '2026-06-25',
    })
  })

  it("masque les actions d'une absence déjà commencée", async () => {
    server.use(...presenceHandlers([absence({ can_edit: false, can_delete: false })]))

    renderWithProviders(<PresencePage />, { withAuth: true })

    expect(await screen.findByText(/contactez l’accueil/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Modifier/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Supprimer/ })).not.toBeInTheDocument()
  })

  it('rend le focus au bouton qui a ouvert le formulaire', async () => {
    const user = userEvent.setup()
    server.use(...presenceHandlers([absence()]))

    renderWithProviders(<PresencePage />, { withAuth: true })

    const edit = await screen.findByRole('button', { name: /^Modifier/ })
    await user.click(edit)

    // Le bouton de déclaration ne se prétend pas déplié pendant une édition.
    expect(screen.getByRole('button', { name: 'Marquer une absence' })).toHaveAttribute(
      'aria-expanded',
      'false',
    )

    await user.click(screen.getByRole('button', { name: 'Annuler' }))

    await waitFor(() => expect(screen.getByRole('button', { name: /^Modifier/ })).toHaveFocus())
  })

  it('supprime une absence après confirmation', async () => {
    const user = userEvent.setup()
    const deleteSpy = vi.fn()
    server.use(
      ...presenceHandlers([absence({ date_end: null, period: 'morning' })]),
      http.delete('/api/absences/5', () => {
        deleteSpy()
        return HttpResponse.json({ message: 'Supprimé.' })
      }),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /^Supprimer/ }))
    // Confirmation accessible avant l'action destructrice.
    expect(deleteSpy).not.toHaveBeenCalled()
    await user.click(await screen.findByRole('button', { name: /oui, supprimer/i }))

    await waitFor(() => expect(deleteSpy).toHaveBeenCalledTimes(1))
  })

  it('bascule sur l’historique complet (?all=1), sans rappel « absence commencée »', async () => {
    const user = userEvent.setup()
    server.use(
      ...presenceHandlers(
        [],
        [
          absence({
            id: 9,
            date_start: '2025-01-10',
            date_end: '2025-01-12',
            can_edit: false,
            can_delete: false,
          }),
        ],
      ),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    expect(await screen.findByText('Aucune absence à venir.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Voir l’historique' }))

    expect(await screen.findByText('du 10/01/2025 au 12/01/2025')).toBeInTheDocument()
    expect(screen.queryByText(/Absence commencée/)).not.toBeInTheDocument()
  })
})

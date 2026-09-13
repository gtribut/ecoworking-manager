import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { MEMBER_PERMISSIONS, makeAuthUser, renderWithProviders } from '@/test/utils'
import { PresencePage } from './PresencePage'

/** Résident doté d'un bureau attitré : seul profil autorisé sur cette page. */
function resident(): AuthUser {
  return makeAuthUser({ has_desk: true, permissions: MEMBER_PERMISSIONS })
}

describe('PresencePage', () => {
  it('liste les absences existantes', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(resident())),
      http.get('/api/presence', () =>
        HttpResponse.json({
          present_days: [],
          absences: [
            {
              id: 5,
              date_start: '2026-06-20',
              date_end: '2026-06-22',
              period: 'full_day',
              recurrence_type: 'none',
              recurrence_day_of_week: null,
              notes: null,
            },
          ],
        }),
      ),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    expect(await screen.findByRole('button', { name: /supprimer/i })).toBeInTheDocument()
    expect(screen.getByText('20/06/2026 → 22/06/2026')).toBeInTheDocument()
  })

  it('déclare une absence ponctuelle', async () => {
    const user = userEvent.setup()
    const createSpy = vi.fn()
    server.use(
      http.get('/api/user', () => HttpResponse.json(resident())),
      http.get('/api/presence', () => HttpResponse.json({ present_days: [], absences: [] })),
      http.post('/api/absences', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json({ data: { id: 1 } }, { status: 201 })
      }),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.type(await screen.findByLabelText('Date de début'), '2026-06-25')
    await user.click(screen.getByRole('button', { name: /enregistrer l’absence/i }))

    await waitFor(() => expect(screen.getByText('Absence enregistrée.')).toBeInTheDocument())
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({
      date_start: '2026-06-25',
      recurrence_type: 'none',
    })
  })

  it('exige un jour de la semaine pour une absence hebdomadaire', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/user', () => HttpResponse.json(resident())),
      http.get('/api/presence', () => HttpResponse.json({ present_days: [], absences: [] })),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.type(await screen.findByLabelText('Date de début'), '2026-06-25')
    await user.selectOptions(screen.getByLabelText('Récurrence'), 'weekly')
    await user.click(screen.getByRole('button', { name: /enregistrer l’absence/i }))

    expect(await screen.findByText(/choisissez un jour de la semaine/i)).toBeInTheDocument()
  })

  it('supprime une absence après confirmation', async () => {
    const user = userEvent.setup()
    const deleteSpy = vi.fn()
    server.use(
      http.get('/api/user', () => HttpResponse.json(resident())),
      http.get('/api/presence', () =>
        HttpResponse.json({
          present_days: [],
          absences: [
            {
              id: 5,
              date_start: '2026-06-20',
              date_end: null,
              period: 'morning',
              recurrence_type: 'none',
              recurrence_day_of_week: null,
              notes: null,
            },
          ],
        }),
      ),
      http.delete('/api/absences/5', () => {
        deleteSpy()
        return HttpResponse.json({ message: 'Supprimé.' })
      }),
    )

    renderWithProviders(<PresencePage />, { withAuth: true })

    await user.click(await screen.findByRole('button', { name: /supprimer l’absence/i }))
    // Confirmation accessible avant l'action destructrice.
    expect(deleteSpy).not.toHaveBeenCalled()
    await user.click(await screen.findByRole('button', { name: /oui, supprimer/i }))

    await waitFor(() => expect(deleteSpy).toHaveBeenCalledTimes(1))
  })
})

import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { TicketsPage } from './TicketsPage'

describe('TicketsPage', () => {
  it('affiche les soldes de tickets', async () => {
    server.use(
      http.get('/api/tickets', () =>
        HttpResponse.json({
          balances: { desk_half_day: 4, meeting_room_half_day: 2 },
          tickets: [],
        }),
      ),
    )

    renderWithProviders(<TicketsPage />)

    expect(await screen.findByText('4')).toBeInTheDocument()
    expect(screen.getByText('Demi-journées bureau nomade')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
  })

  it('cherche les bureaux disponibles puis réserve', async () => {
    const user = userEvent.setup()
    const createSpy = vi.fn()
    server.use(
      http.get('/api/tickets', () =>
        HttpResponse.json({
          balances: { desk_half_day: 3, meeting_room_half_day: 0 },
          tickets: [],
        }),
      ),
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          period: 'full_day',
          count: 1,
          desks: [
            { id: 9, name: 'Bureau 12', floor: 2, svg_desk_id: null, features: [], capacity: 1 },
          ],
        }),
      ),
      http.post('/api/desk-occupations', async ({ request }) => {
        createSpy(await request.json())
        return HttpResponse.json(
          {
            data: {
              id: 1,
              desk_id: 9,
              desk_name: 'Bureau 12',
              date: '2026-06-12',
              period: 'full_day',
              status: 'confirmed',
            },
          },
          { status: 201 },
        )
      }),
    )

    renderWithProviders(<TicketsPage />)

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))
    const heading = await screen.findByRole('heading', { name: /bureau\(x\) disponible/i })
    const list = heading.parentElement as HTMLElement
    await user.click(within(list).getByRole('button', { name: /réserver le bureau/i }))

    await waitFor(() =>
      expect(screen.getByText('Bureau « Bureau 12 » réservé.')).toBeInTheDocument(),
    )
    expect(createSpy.mock.calls[0]?.[0]).toMatchObject({ desk_id: 9, period: 'full_day' })
  })

  it('refuse un week-end côté client (jours ouvrés uniquement) sans appeler l’API', async () => {
    const user = userEvent.setup()
    const availabilitySpy = vi.fn()
    server.use(
      http.get('/api/tickets', () =>
        HttpResponse.json({
          balances: { desk_half_day: 3, meeting_room_half_day: 0 },
          tickets: [],
        }),
      ),
      http.get('/api/desks/availability', () => {
        availabilitySpy()
        return HttpResponse.json({ date: '', period: 'full_day', count: 0, desks: [] })
      }),
    )

    renderWithProviders(<TicketsPage />)

    const dateInput = await screen.findByLabelText('Date')
    // Un samedi arbitraire dans le futur lointain (stable quel que soit le jour du run).
    await user.clear(dateInput)
    await user.type(dateInput, '2030-07-06')
    await user.click(screen.getByRole('button', { name: /voir les bureaux disponibles/i }))

    expect(
      await screen.findByText(
        'Les bureaux nomades ne sont réservables que les jours ouvrés (lundi à vendredi).',
      ),
    ).toBeInTheDocument()
    expect(availabilitySpy).not.toHaveBeenCalled()
  })

  it('affiche le message d’erreur 422 quand plus de ticket', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/tickets', () =>
        HttpResponse.json({
          balances: { desk_half_day: 1, meeting_room_half_day: 0 },
          tickets: [],
        }),
      ),
      http.get('/api/desks/availability', () =>
        HttpResponse.json({
          date: '2026-06-12',
          period: 'full_day',
          count: 1,
          desks: [
            { id: 9, name: 'Bureau 12', floor: 2, svg_desk_id: null, features: [], capacity: 1 },
          ],
        }),
      ),
      http.post('/api/desk-occupations', () =>
        HttpResponse.json({ message: 'Plus aucun ticket bureau disponible.' }, { status: 422 }),
      ),
    )

    renderWithProviders(<TicketsPage />)

    await user.click(await screen.findByRole('button', { name: /voir les bureaux disponibles/i }))
    const heading = await screen.findByRole('heading', { name: /bureau\(x\) disponible/i })
    const list = heading.parentElement as HTMLElement
    await user.click(within(list).getByRole('button', { name: /réserver le bureau/i }))

    expect(await screen.findByText('Plus aucun ticket bureau disponible.')).toBeInTheDocument()
  })
})

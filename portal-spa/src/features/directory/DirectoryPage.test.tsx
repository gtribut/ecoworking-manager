import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DirectoryPage } from './DirectoryPage'
import type { DirectoryEntry } from './types'

function makeEntry(overrides: Partial<DirectoryEntry> = {}): DirectoryEntry {
  return {
    id: 1,
    first_name: 'Marie',
    last_name: 'Durand',
    photo_path: null,
    job_title: 'Designer',
    bio: 'Design de services.',
    interests: 'Vélo, céramique',
    linkedin_url: 'https://linkedin.com/in/marie',
    website_url: null,
    company: 'Acme Studio',
    ...overrides,
  }
}

function page(data: DirectoryEntry[], lastPage = 1) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: lastPage, per_page: 24, total: data.length },
  })
}

describe('DirectoryPage', () => {
  it('affiche les coworkers opt-in : nom, entreprise, poste, liens', async () => {
    server.use(
      http.get('/api/directory', () =>
        page([
          makeEntry(),
          makeEntry({
            id: 2,
            first_name: 'Ali',
            last_name: 'Benali',
            company: 'SCOP Zéro',
            job_title: 'Développeur',
          }),
        ]),
      ),
    )

    renderWithProviders(<DirectoryPage />)

    expect(await screen.findByText('Marie Durand')).toBeInTheDocument()
    expect(screen.getByText('Acme Studio')).toBeInTheDocument()
    expect(screen.getByText('Designer')).toBeInTheDocument()
    expect(screen.getByText('Ali Benali')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /LinkedIn de Marie Durand/i })).toHaveAttribute(
      'href',
      'https://linkedin.com/in/marie',
    )
  })

  it('envoie la recherche au serveur via le paramètre q', async () => {
    const seenQ: (string | null)[] = []
    server.use(
      http.get('/api/directory', ({ request }) => {
        seenQ.push(new URL(request.url).searchParams.get('q'))
        return page([makeEntry()])
      }),
    )

    renderWithProviders(<DirectoryPage />)
    await screen.findByText('Marie Durand')

    const user = userEvent.setup()
    await user.type(screen.getByLabelText('Rechercher un coworker'), 'acme')
    await user.click(screen.getByRole('button', { name: 'Rechercher' }))

    await waitFor(() => expect(seenQ).toContain('acme'))
  })

  it('affiche un état vide adapté à la recherche', async () => {
    server.use(http.get('/api/directory', () => page([])))

    renderWithProviders(<DirectoryPage />)

    expect(
      await screen.findByText('Aucun coworker ne s’affiche dans l’annuaire pour le moment.'),
    ).toBeInTheDocument()
  })

  it('explique le refus d’accès (403 : external sans view-annuaire)', async () => {
    server.use(
      http.get('/api/directory', () =>
        HttpResponse.json({ message: 'Forbidden' }, { status: 403 }),
      ),
    )

    renderWithProviders(<DirectoryPage />)

    // Message générique (PRD §3.8.2, lot G) : jamais le message serveur brut sur un 403.
    expect(await screen.findByText('Accès refusé.')).toBeInTheDocument()
  })
})

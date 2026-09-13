import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { InvoicesPage } from './InvoicesPage'

function makeInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    number: 'EW-2026-00001',
    status: 'paid',
    is_credit_note: false,
    issued_at: '2026-05-01',
    due_at: '2026-05-15',
    total_ht: '100.00',
    total_vat: '20.00',
    total_ttc: '120.00',
    amount_paid: '120.00',
    pdf_available: true,
    ...overrides,
  }
}

function page(data: unknown[], lastPage = 1) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: lastPage, per_page: 20, total: data.length },
  })
}

/** Le bloc entité du module administratif interroge /api/billing/entity. */
function withEntities(entities: unknown[] = []) {
  server.use(http.get('/api/billing/entity', () => HttpResponse.json({ data: entities })))
}

beforeEach(() => {
  withEntities()
})

describe('InvoicesPage', () => {
  it('affiche les factures avec un lien de téléchargement quand le PDF est dispo', async () => {
    server.use(http.get('/api/invoices', () => page([makeInvoice()])))

    renderWithProviders(<InvoicesPage />)

    expect(await screen.findByText('EW-2026-00001')).toBeInTheDocument()
    const table = screen.getByRole('table')
    expect(within(table).getByText('Payée')).toBeInTheDocument()
    const link = screen.getByRole('link', { name: /télécharger/i })
    expect(link).toHaveAttribute('href', '/api/invoices/1/pdf')
  })

  it('affiche un message quand aucune facture', async () => {
    server.use(http.get('/api/invoices', () => page([])))

    renderWithProviders(<InvoicesPage />)

    expect(await screen.findByText('Aucune facture pour le moment.')).toBeInTheDocument()
  })

  it('signale un PDF indisponible sans lien', async () => {
    server.use(
      http.get('/api/invoices', () =>
        page([
          makeInvoice({ id: 2, number: 'EW-2026-00002', status: 'sent', pdf_available: false }),
        ]),
      ),
    )

    renderWithProviders(<InvoicesPage />)

    expect(await screen.findByText('EW-2026-00002')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /télécharger/i })).not.toBeInTheDocument()
    expect(screen.getByText('Indisponible')).toBeInTheDocument()
  })

  it('envoie les filtres année, mois, statut et recherche à l’API', async () => {
    const user = userEvent.setup()
    const calls: string[] = []
    server.use(
      http.get('/api/invoices', ({ request }) => {
        calls.push(new URL(request.url).search)
        return page([makeInvoice()])
      }),
    )

    renderWithProviders(<InvoicesPage />)
    await screen.findByText('EW-2026-00001')

    // Le mois n'est activable qu'une fois l'année choisie (PRD §3.6.2).
    expect(screen.getByLabelText('Mois')).toBeDisabled()

    const year = String(new Date().getFullYear())
    await user.selectOptions(screen.getByLabelText('Année'), year)
    await waitFor(() => expect(screen.getByLabelText('Mois')).toBeEnabled())
    await user.selectOptions(screen.getByLabelText('Mois'), '3')
    await user.selectOptions(screen.getByLabelText('Statut'), 'overdue')
    await user.type(screen.getByLabelText('Numéro de facture'), '00042')
    await user.click(screen.getByRole('button', { name: 'Rechercher' }))

    await waitFor(() => {
      const last = calls.at(-1) ?? ''
      expect(last).toContain(`year=${year}`)
      expect(last).toContain('month=3')
      expect(last).toContain('status=overdue')
      expect(last).toContain('q=00042')
    })
  })

  it('trie sur demande et reflète l’état dans aria-sort', async () => {
    const user = userEvent.setup()
    const calls: string[] = []
    server.use(
      http.get('/api/invoices', ({ request }) => {
        calls.push(new URL(request.url).search)
        return page([makeInvoice()])
      }),
    )

    renderWithProviders(<InvoicesPage />)
    await screen.findByText('EW-2026-00001')

    // Tri par défaut : date décroissante.
    expect(screen.getByRole('columnheader', { name: /date/i })).toHaveAttribute(
      'aria-sort',
      'descending',
    )

    await user.click(screen.getByRole('button', { name: /numéro/i }))

    await waitFor(() => {
      expect(screen.getByRole('columnheader', { name: /numéro/i })).toHaveAttribute(
        'aria-sort',
        'ascending',
      )
    })
    expect(screen.getByRole('columnheader', { name: /date/i })).toHaveAttribute('aria-sort', 'none')
    // Le focus reste sur l'en-tête activé (navigation clavier, CLAUDE.md §3.5).
    expect(screen.getByRole('button', { name: /numéro/i })).toHaveFocus()
    await waitFor(() => {
      const last = calls.at(-1) ?? ''
      expect(last).toContain('sort=number')
      expect(last).toContain('direction=asc')
    })
  })

  it('propose de réinitialiser des filtres qui ne renvoient aucune facture', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/invoices', ({ request }) => {
        const hasFilter = new URL(request.url).searchParams.has('status')
        return page(hasFilter ? [] : [makeInvoice()])
      }),
    )

    renderWithProviders(<InvoicesPage />)
    await screen.findByText('EW-2026-00001')

    await user.selectOptions(screen.getByLabelText('Statut'), 'overdue')

    expect(
      await screen.findByText('Aucune facture ne correspond à ces filtres.'),
    ).toBeInTheDocument()

    const [resetButton] = screen.getAllByRole('button', { name: 'Réinitialiser les filtres' })
    await user.click(resetButton as HTMLElement)

    expect(await screen.findByText('EW-2026-00001')).toBeInTheDocument()
    expect(screen.getByLabelText('Statut')).toHaveValue('')
  })

  it('affiche le bloc entité du module administratif, masqué sans entité', async () => {
    server.use(http.get('/api/invoices', () => page([makeInvoice()])))
    withEntities([
      {
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
        payment_method: 'sepa',
        payment_method_label: 'Prélèvement SEPA',
        iban_last4: '1234',
      },
    ])

    renderWithProviders(<InvoicesPage />)

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Mon entreprise' }),
    ).toBeInTheDocument()
    expect(screen.getByText('•••• 1234')).toBeInTheDocument()
  })
})

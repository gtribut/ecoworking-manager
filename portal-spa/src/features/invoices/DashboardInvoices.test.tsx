import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DashboardInvoices } from './DashboardInvoices'
import type { Invoice } from './types'

function invoice(id: number, overrides: Partial<Invoice> = {}): Invoice {
  return {
    id,
    number: `EW-2026-0000${id}`,
    status: 'paid',
    is_credit_note: false,
    issued_at: '2026-09-01',
    due_at: '2026-09-15',
    total_ht: '100.00',
    total_vat: '20.00',
    total_ttc: '120.00',
    amount_paid: '120.00',
    pdf_available: true,
    ...overrides,
  }
}

describe('DashboardInvoices (bandeau bento, PRD §3.3.2, maquette C14)', () => {
  it('affiche 3 factures max avec numéro, date, montant, statut et lien PDF, puis le lien « toutes mes factures »', async () => {
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [
            invoice(1, { status: 'overdue' }),
            invoice(2, { status: 'sent', pdf_available: false }),
            invoice(3),
            invoice(4),
          ],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 4 },
        }),
      ),
    )

    renderWithProviders(<DashboardInvoices />)

    expect(await screen.findByText('EW-2026-00001')).toBeInTheDocument()
    expect(screen.getByText('EW-2026-00003')).toBeInTheDocument()
    expect(screen.queryByText('EW-2026-00004')).not.toBeInTheDocument()
    // Date · montant TTC sur la même ligne (maquette C14 : « 1 sept. 2026 · 1 064,34 € »).
    expect(screen.getAllByText('01/09/2026 · 120,00 €')).toHaveLength(3)
    expect(screen.getByText('En retard')).toBeInTheDocument()
    expect(screen.getByText('En attente')).toBeInTheDocument()
    expect(screen.getByText('Indisponible')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /PDF de la facture EW-2026-00001/ })).toHaveAttribute(
      'href',
      '/api/invoices/1/pdf',
    )
    expect(screen.getByRole('link', { name: 'Toutes mes factures' })).toHaveAttribute(
      'href',
      '/invoices',
    )
  })

  it('affiche l’état vide', async () => {
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        }),
      ),
    )

    renderWithProviders(<DashboardInvoices />)

    expect(await screen.findByText('Aucune facture pour le moment.')).toBeInTheDocument()
  })

  it('garde un titre générique, sans requête ni nom d’entité (review U4a)', async () => {
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [invoice(1)],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        }),
      ),
    )
    // Pas de handler pour /api/billing/entity : une requête vers cette route
    // ferait échouer le test (onUnhandledRequest: 'error', cf. setup.ts) —
    // preuve que le composant ne l'appelle plus.

    renderWithProviders(<DashboardInvoices />)

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Mes dernières factures' }),
    ).toBeInTheDocument()
  })
})

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

/** Bloc entité (PRD §3.6.4) : la tuile ne fait que compléter le titre du bandeau. */
function withEntities(entities: unknown[] = []) {
  server.use(http.get('/api/billing/entity', () => HttpResponse.json({ data: entities })))
}

describe('DashboardInvoices (bandeau bento, PRD §3.3.2, maquette C14)', () => {
  it('affiche 3 factures max avec numéro, date, statut et lien PDF, puis le lien « toutes mes factures »', async () => {
    withEntities()
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
    withEntities()
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

  it('complète le titre par le nom de l’entité quand le membre n’en a qu’une seule', async () => {
    withEntities([
      {
        id: 5,
        entity_type: 'company',
        name: 'Atelier Lumière',
        legal_name: 'Atelier Lumière',
        legal_form: 'SARL',
        siret: null,
        vat_number: null,
        billing_email: null,
        address: { line1: null, line2: null, postal_code: null, city: null, country: null },
      },
    ])
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [invoice(1)],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        }),
      ),
    )

    renderWithProviders(<DashboardInvoices />)

    expect(
      await screen.findByRole('heading', {
        level: 2,
        name: 'Mes dernières factures · Atelier Lumière',
      }),
    ).toBeInTheDocument()
  })

  it('garde le titre générique sans entité unique (aucune ou plusieurs)', async () => {
    withEntities()
    server.use(
      http.get('/api/invoices', () =>
        HttpResponse.json({
          data: [invoice(1)],
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
        }),
      ),
    )

    renderWithProviders(<DashboardInvoices />)

    expect(
      await screen.findByRole('heading', { level: 2, name: 'Mes dernières factures' }),
    ).toBeInTheDocument()
  })
})

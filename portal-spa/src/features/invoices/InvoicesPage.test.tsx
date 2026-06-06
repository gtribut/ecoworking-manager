import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { InvoicesPage } from './InvoicesPage'

function page(data: unknown[], lastPage = 1) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: lastPage, per_page: 20, total: data.length },
  })
}

describe('InvoicesPage', () => {
  it('affiche les factures avec un lien de téléchargement quand le PDF est dispo', async () => {
    server.use(
      http.get('/api/invoices', () =>
        page([
          {
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
          },
        ]),
      ),
    )

    renderWithProviders(<InvoicesPage />)

    expect(await screen.findByText('EW-2026-00001')).toBeInTheDocument()
    expect(screen.getByText('Payée')).toBeInTheDocument()
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
          {
            id: 2,
            number: 'EW-2026-00002',
            status: 'sent',
            is_credit_note: false,
            issued_at: '2026-05-02',
            due_at: null,
            total_ht: '50.00',
            total_vat: '10.00',
            total_ttc: '60.00',
            amount_paid: '0.00',
            pdf_available: false,
          },
        ]),
      ),
    )

    renderWithProviders(<InvoicesPage />)

    expect(await screen.findByText('EW-2026-00002')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /télécharger/i })).not.toBeInTheDocument()
    expect(screen.getByText('Indisponible')).toBeInTheDocument()
  })
})

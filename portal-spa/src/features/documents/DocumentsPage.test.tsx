import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DocumentsPage } from './DocumentsPage'
import type { AdministrativeDocument, InternalDocument } from './types'

function member(permissions: string[] = ['validate-internal-document']): AuthUser {
  return {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    has_desk: false,
    two_factor_enabled: false,
    roles: [],
    permissions,
  }
}

function internalDocument(overrides: Partial<InternalDocument> = {}): InternalDocument {
  return {
    id: 1,
    type: 'charter',
    title: 'Charte interne',
    version: '2.0',
    body: null,
    published_at: '2026-06-01T10:00:00+02:00',
    pdf_available: true,
    is_validated: false,
    validated_at: null,
    ...overrides,
  }
}

function administrativeDocument(
  overrides: Partial<AdministrativeDocument> = {},
): AdministrativeDocument {
  return {
    id: 11,
    type: 'domiciliation',
    title: 'Contrat de domiciliation',
    document_date: '2026-01-15',
    company_name: 'ACME SARL',
    pdf_available: true,
    ...overrides,
  }
}

function administrativePage(data: AdministrativeDocument[]) {
  return HttpResponse.json({
    data,
    meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
  })
}

function renderPage() {
  return renderWithProviders(<DocumentsPage />, { withAuth: true })
}

describe('DocumentsPage', () => {
  it('liste les documents à valider avec téléchargement et bouton Valider', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () => HttpResponse.json({ data: [internalDocument()] })),
    )

    renderPage()

    expect(await screen.findByText('Charte interne')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Valider' })).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Télécharger le document Charte interne (PDF)' }),
    ).toHaveAttribute('href', '/api/documents/internal/1/pdf')
  })

  it('valide un document après confirmation puis affiche la date de validation', async () => {
    let validated = false
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () =>
        HttpResponse.json({
          data: [
            internalDocument({
              is_validated: validated,
              validated_at: validated ? '2026-07-02T09:00:00+02:00' : null,
            }),
          ],
        }),
      ),
      http.post('/api/documents/internal/1/validation', () => {
        validated = true
        return HttpResponse.json({ message: 'Document validé.' }, { status: 201 })
      }),
    )

    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Valider' }))
    // Confirmation en deux temps (pas de window.confirm).
    await user.click(screen.getByRole('button', { name: 'Confirmer' }))

    expect(await screen.findByText('Validé le 02/07/2026')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Valider' })).not.toBeInTheDocument()
  })

  it('affiche l’état « à jour » quand tout est validé', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () =>
        HttpResponse.json({
          data: [
            internalDocument({ is_validated: true, validated_at: '2026-06-15T10:00:00+02:00' }),
          ],
        }),
      ),
    )

    renderPage()

    expect(await screen.findByText('Tous vos documents sont à jour.')).toBeInTheDocument()
    expect(screen.getByText('Validé le 15/06/2026')).toBeInTheDocument()
  })

  it('masque la section administrative sans permission billing', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
    )

    renderPage()

    await screen.findByText('Tous vos documents sont à jour.')
    expect(screen.queryByText('Mes documents administratifs')).not.toBeInTheDocument()
  })

  it('liste les documents administratifs pour un contact facturation', async () => {
    server.use(
      http.get('/api/user', () =>
        HttpResponse.json(member(['validate-internal-document', 'view-entity-admin-documents'])),
      ),
      http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
      http.get('/api/documents/administrative', () =>
        administrativePage([administrativeDocument()]),
      ),
    )

    renderPage()

    expect(
      await screen.findByRole('heading', { name: 'Mes documents administratifs' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('Contrat de domiciliation')).toBeInTheDocument()
    expect(screen.getByText('Domiciliation')).toBeInTheDocument()
    expect(screen.getByText('ACME SARL')).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Télécharger le document Contrat de domiciliation' }),
    ).toHaveAttribute('href', '/api/documents/administrative/11/pdf')
  })
})

import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { DashboardDocumentsToValidate } from './DashboardDocumentsToValidate'
import type { InternalDocument } from './types'

function member(): AuthUser {
  return {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    has_desk: false,
    two_factor_enabled: false,
    roles: [],
    permissions: ['validate-internal-document'],
  }
}

function internalDocument(
  id: number,
  title: string,
  overrides: Partial<InternalDocument> = {},
): InternalDocument {
  return {
    id,
    type: 'charter',
    title,
    version: '1.0',
    body: null,
    published_at: '2026-06-01T10:00:00+02:00',
    pdf_available: true,
    is_validated: false,
    validated_at: null,
    ...overrides,
  }
}

/**
 * KPI « Document à valider » du dashboard bento (maquette C14) : ces tests
 * remplacent l'ancien bloc pleine liste (téléchargement + validation
 * directe, désormais sur `/documents`, cf. `InternalDocumentItem` et
 * `DocumentsPage.test.tsx`, hors périmètre U4a) par une tuile compacte.
 */
describe('DashboardDocumentsToValidate (KPI dashboard, PRD §3.3.2)', () => {
  it('affiche le premier titre à valider, le compteur des autres et un lien vers /documents', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () =>
        HttpResponse.json({
          data: [
            internalDocument(1, 'Charte interne'),
            internalDocument(2, 'CGU'),
            internalDocument(3, 'Droit à l’image', {
              is_validated: true,
              validated_at: '2026-06-15T10:00:00+02:00',
            }),
          ],
        }),
      ),
    )

    renderWithProviders(<DashboardDocumentsToValidate />, { withAuth: true })

    expect(await screen.findByText('Document à valider')).toBeInTheDocument()
    expect(screen.getByText('Charte interne')).toBeInTheDocument()
    const link = screen.getByRole('link', { name: /\+1 autre\(s\) · Lire et valider/ })
    expect(link).toHaveAttribute('href', '/documents')
  })

  it('affiche « Documents à jour » quand rien n’est à valider (recette R-06)', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
    )

    renderWithProviders(<DashboardDocumentsToValidate />, { withAuth: true })

    expect(await screen.findByText('Documents à jour')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir mes documents' })).toHaveAttribute(
      'href',
      '/documents',
    )
  })

  it('affiche un état indisponible sans planter si la requête échoue', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () =>
        HttpResponse.json({ message: 'Erreur' }, { status: 500 }),
      ),
    )

    renderWithProviders(<DashboardDocumentsToValidate />, { withAuth: true })

    expect(await screen.findByText('Indisponible')).toBeInTheDocument()
  })
})

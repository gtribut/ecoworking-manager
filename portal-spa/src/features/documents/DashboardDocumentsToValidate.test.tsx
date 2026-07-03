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

describe('DashboardDocumentsToValidate', () => {
  it('liste uniquement les documents non validés, avec le lien vers la page Documents', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () =>
        HttpResponse.json({
          data: [
            internalDocument(1, 'Charte interne'),
            internalDocument(2, 'CGU', {
              is_validated: true,
              validated_at: '2026-06-15T10:00:00+02:00',
            }),
          ],
        }),
      ),
    )

    renderWithProviders(<DashboardDocumentsToValidate />, { withAuth: true })

    expect(await screen.findByText('Charte interne')).toBeInTheDocument()
    expect(screen.queryByText('CGU')).not.toBeInTheDocument() // déjà validé → masqué
    expect(screen.getByRole('button', { name: 'Valider' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Voir tous mes documents →' })).toHaveAttribute(
      'href',
      '/documents',
    )
  })

  it('affiche « à jour » quand rien n’est à valider (bloc non bloquant, PRD §5.3)', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(member())),
      http.get('/api/documents/internal', () => HttpResponse.json({ data: [] })),
    )

    renderWithProviders(<DashboardDocumentsToValidate />, { withAuth: true })

    expect(await screen.findByText('Tous vos documents sont à jour.')).toBeInTheDocument()
  })
})

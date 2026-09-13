import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { type RenderResult, render } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router'
import { Toaster } from 'sonner'
import { AuthProvider } from '@/features/auth/AuthContext'
import type { AuthUser } from '@/features/auth/types'

/** QueryClient isolé par test, sans retry (pas de flakiness ni d'attente). */
export function makeTestQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  })
}

export function renderWithProviders(
  ui: ReactElement,
  { route = '/', withAuth = false }: { route?: string; withAuth?: boolean } = {},
): RenderResult {
  const client = makeTestQueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    const tree = withAuth ? <AuthProvider>{children}</AuthProvider> : children
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[route]}>
          {tree}
          {/* Monté systématiquement : les toasts (PRD §3.1/§3.8.4, lot G) sont
              assertables via `screen.findByText(...)` sans configuration par test. */}
          <Toaster />
        </MemoryRouter>
      </QueryClientProvider>
    )
  }

  return render(ui, { wrapper: Wrapper })
}

/**
 * Compositions rôle → permissions, miroir de `database/seeders/PermissionSeeder.php`
 * (matrice PRD §2.5). À garder synchronisées avec le seeder.
 */

/** Tronc commun resident / staff / additional (`$memberBase` + annuaire). */
export const MEMBER_PERMISSIONS: string[] = [
  'view-own-bookings',
  'view-bookings-calendar',
  'create-own-booking',
  'manage-own-booking',
  'register-event',
  'validate-internal-document',
  'view-annuaire',
]

/** external : réserve via ticket payant, pas d'annuaire. */
export const EXTERNAL_PERMISSIONS: string[] = [
  'view-own-bookings',
  'view-bookings-calendar',
  'create-paid-booking',
  'manage-own-booking',
  'register-event',
  'validate-internal-document',
]

/** billing_contact : rôle additionnel, visibilité facturation seulement. */
export const BILLING_PERMISSIONS: string[] = [
  'view-billing-section',
  'view-entity-invoices',
  'view-entity-admin-documents',
  'request-entity-modification',
]

/**
 * Utilisateur tel que renvoyé par `GET /api/user`, pour les mocks msw.
 * Par défaut : aucun rôle d'usage, aucun bureau attitré.
 */
export function makeAuthUser(overrides: Partial<AuthUser> = {}): AuthUser {
  return {
    id: 1,
    first_name: 'Alex',
    last_name: 'Martin',
    email: 'alex@ex.fr',
    theme: null,
    has_desk: false,
    two_factor_enabled: false,
    roles: [],
    permissions: [],
    ...overrides,
  }
}

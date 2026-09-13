import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '@/test/utils'
import { EmptyState } from './EmptyState'

describe('EmptyState', () => {
  it('affiche le titre, la description et le CTA', () => {
    renderWithProviders(
      <EmptyState
        title="Aucune réservation."
        description="Réservez votre première salle"
        cta={{ label: 'Réserver', to: '/bookings' }}
      />,
    )

    expect(screen.getByText('Aucune réservation.')).toBeInTheDocument()
    expect(screen.getByText('Réservez votre première salle')).toBeInTheDocument()
    const link = screen.getByRole('link', { name: /Réserver/ })
    expect(link).toHaveAttribute('href', '/bookings')
  })

  it('fonctionne sans CTA ni description', () => {
    renderWithProviders(<EmptyState title="Aucune actualité pour le moment." />)
    expect(screen.getByText('Aucune actualité pour le moment.')).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
})

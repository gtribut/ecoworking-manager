import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { PageContainer } from './PageContainer'

describe('PageContainer', () => {
  it('applique la largeur maximale demandée', () => {
    const { rerender } = render(
      <PageContainer width="narrow">
        <p>contenu</p>
      </PageContainer>,
    )
    expect(screen.getByText('contenu').parentElement).toHaveClass('max-w-3xl')

    rerender(
      <PageContainer width="wide">
        <p>contenu</p>
      </PageContainer>,
    )
    expect(screen.getByText('contenu').parentElement).toHaveClass('max-w-6xl')

    rerender(
      <PageContainer width="full">
        <p>contenu</p>
      </PageContainer>,
    )
    expect(screen.getByText('contenu').parentElement).toHaveClass('max-w-none')
  })

  it('réserve la place de la bottom nav mobile et garde les gouttières', () => {
    render(
      <PageContainer>
        <p>contenu</p>
      </PageContainer>,
    )

    const container = screen.getByText('contenu').parentElement
    expect(container).toHaveClass('px-4', 'pb-28', 'md:px-8')
    // Largeur par défaut : `wide`.
    expect(container).toHaveAttribute('data-width', 'wide')
  })
})

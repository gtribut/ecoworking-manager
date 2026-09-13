import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { MarkdownContent } from './MarkdownContent'

describe('MarkdownContent', () => {
  it('rend le gras, les listes et les liens', () => {
    const { container } = render(
      <MarkdownContent
        markdown={'**gras** et *italique*\n\n- un\n- deux\n\n[Ecoworking](https://ecoworking.fr)'}
      />,
    )

    expect(container.querySelector('strong')).toHaveTextContent('gras')
    expect(container.querySelector('em')).toHaveTextContent('italique')
    expect(container.querySelectorAll('li')).toHaveLength(2)
    expect(screen.getByRole('link', { name: 'Ecoworking' })).toHaveAttribute(
      'href',
      'https://ecoworking.fr',
    )
  })

  it('ouvre les liens externes dans un nouvel onglet, sans exposer window.opener', () => {
    render(<MarkdownContent markdown="[site](https://example.com)" />)

    const link = screen.getByRole('link', { name: 'site' })
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
  })

  it('neutralise les balises <script> (XSS)', () => {
    const { container } = render(
      <MarkdownContent markdown={'Bonjour <script>window.__pwned = true</script> !'} />,
    )

    expect(container.querySelector('script')).not.toBeInTheDocument()
    expect(container.innerHTML).not.toContain('<script')
  })

  it('neutralise les gestionnaires d’évènements inline (onerror)', () => {
    const { container } = render(
      <MarkdownContent markdown='<img src="x" onerror="window.__pwned = true">' />,
    )

    expect(container.innerHTML).not.toContain('onerror')
    expect(container.querySelector('img')).not.toBeInTheDocument()
  })

  it('retire les images (hors allow-list)', () => {
    const { container } = render(
      <MarkdownContent markdown="![alt](https://example.com/photo.jpg)" />,
    )

    expect(container.querySelector('img')).not.toBeInTheDocument()
  })
})

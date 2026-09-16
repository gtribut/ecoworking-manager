import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Textarea } from './textarea'

describe('Textarea', () => {
  it('avec une erreur : aria-invalid, message relié via aria-describedby', () => {
    render(<Textarea id="bio" aria-label="Présentation" error="500 caractères maximum." />)
    const textarea = screen.getByLabelText('Présentation')

    expect(textarea).toHaveAttribute('aria-invalid', 'true')
    const message = screen.getByText('500 caractères maximum.')
    expect(textarea.getAttribute('aria-describedby')).toContain(message.id)
  })
})

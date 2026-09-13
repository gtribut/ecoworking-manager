import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Avatar } from './Avatar'

const photo = {
  sm: '/api/users/7/photo/80',
  md: '/api/users/7/photo/200',
  lg: '/api/users/7/photo/400',
}

describe('Avatar', () => {
  it('affiche les initiales quand il n’y a pas de photo (PRD §3.4.2)', () => {
    render(<Avatar firstName="Emma" lastName="Membre" photo={null} />)

    expect(screen.getByText('EM')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  it('affiche la photo avec une alternative nommée, chargée en différé', () => {
    render(<Avatar firstName="Emma" lastName="Membre" photo={photo} />)

    const image = screen.getByRole('img', { name: 'Emma Membre' })
    expect(image).toHaveAttribute('src', '/api/users/7/photo/80')
    expect(image).toHaveAttribute('loading', 'lazy')
  })

  it('choisit le rendu serveur correspondant à la taille demandée', () => {
    render(<Avatar firstName="Emma" lastName="Membre" photo={photo} size="lg" />)

    expect(screen.getByRole('img', { name: 'Emma Membre' })).toHaveAttribute(
      'src',
      '/api/users/7/photo/400',
    )
  })

  it('retombe sur les initiales si l’image ne charge pas', () => {
    render(<Avatar firstName="Emma" lastName="Membre" photo={photo} />)

    fireEvent.error(screen.getByRole('img', { name: 'Emma Membre' }))

    expect(screen.getByText('EM')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  it('grise la photo d’un résident absent (PRD §3.7.3)', () => {
    render(<Avatar firstName="Emma" lastName="Membre" photo={photo} muted />)

    expect(screen.getByRole('img', { name: 'Emma Membre' }).className).toContain('grayscale')
  })
})

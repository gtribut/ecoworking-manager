import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { PhotoSection } from './PhotoSection'

const photo = {
  sm: '/api/users/7/photo/80',
  md: '/api/users/7/photo/200',
  lg: '/api/users/7/photo/400',
}

/** Fichier image factice d'un poids donné (le contenu n'est jamais lu ici). */
function imageFile(name: string, type: string, bytes: number): File {
  const file = new File(['x'], name, { type })
  Object.defineProperty(file, 'size', { value: bytes })
  return file
}

describe('PhotoSection', () => {
  it('affiche l’avatar initiales tant qu’aucune photo n’est déposée', () => {
    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={null} />)

    expect(screen.getByText('EM')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer la photo' })).not.toBeInTheDocument()
  })

  it('envoie le fichier choisi et confirme la mise à jour', async () => {
    const user = userEvent.setup()
    const received = vi.fn()
    server.use(
      http.post('/api/profile/photo', async ({ request }) => {
        const contentType = request.headers.get('content-type') ?? ''
        const body = await request.formData()
        const uploaded = body.get('photo') as Blob | null
        // jsdom perd le nom du fichier à la sérialisation multipart : on
        // vérifie le transport (multipart) et la présence du binaire.
        received({
          multipart: contentType.startsWith('multipart/form-data'),
          bytes: uploaded?.size ?? 0,
        })
        return HttpResponse.json({ photo })
      }),
    )

    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={null} />)

    await user.upload(
      screen.getByLabelText('Choisir une photo'),
      new File(['image-binaire'], 'moi.jpg', { type: 'image/jpeg' }),
    )

    expect(await screen.findByText('Photo de profil mise à jour.')).toBeInTheDocument()
    await waitFor(() =>
      expect(received).toHaveBeenCalledWith({ multipart: true, bytes: 'image-binaire'.length }),
    )
  })

  it('refuse un fichier de plus de 2 Mo sans appeler l’API', async () => {
    const user = userEvent.setup()
    const called = vi.fn()
    server.use(
      http.post('/api/profile/photo', () => {
        called()
        return HttpResponse.json({ photo })
      }),
    )

    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={null} />)

    await user.upload(
      screen.getByLabelText('Choisir une photo'),
      imageFile('enorme.jpg', 'image/jpeg', 3 * 1024 * 1024),
    )

    expect(await screen.findByText('La photo ne doit pas dépasser 2 Mo.')).toBeInTheDocument()
    expect(called).not.toHaveBeenCalled()
  })

  it('refuse un format non accepté sans appeler l’API', async () => {
    // `accept` filtrerait déjà le fichier côté navigateur ; on vérifie ici la
    // garde JS, qui doit tenir même si l'utilisateur force le format.
    const user = userEvent.setup({ applyAccept: false })
    const called = vi.fn()
    server.use(
      http.post('/api/profile/photo', () => {
        called()
        return HttpResponse.json({ photo })
      }),
    )

    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={null} />)

    await user.upload(
      screen.getByLabelText('Choisir une photo'),
      imageFile('cv.pdf', 'application/pdf', 1024),
    )

    expect(await screen.findByText('Formats acceptés : JPG, PNG ou WebP.')).toBeInTheDocument()
    expect(called).not.toHaveBeenCalled()
  })

  it('supprime la photo existante', async () => {
    const user = userEvent.setup()
    server.use(http.delete('/api/profile/photo', () => HttpResponse.json({ photo: null })))

    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={photo} />)

    expect(screen.getByRole('img', { name: 'Emma Membre' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Supprimer la photo' }))

    expect(await screen.findByText('Photo de profil supprimée.')).toBeInTheDocument()
    // Le bouton qui portait le focus vient d'être démonté : le focus doit
    // revenir sur le champ, pas retomber sur <body> (RGAA 12.x).
    expect(screen.getByLabelText('Choisir une photo')).toHaveFocus()
  })

  it('remonte l’erreur de validation du serveur (422)', async () => {
    const user = userEvent.setup()
    server.use(
      http.post('/api/profile/photo', () =>
        HttpResponse.json(
          {
            message: 'Données invalides.',
            errors: { photo: ['La photo doit mesurer au moins 80 × 80 pixels.'] },
          },
          { status: 422 },
        ),
      ),
    )

    renderWithProviders(<PhotoSection firstName="Emma" lastName="Membre" photo={null} />)

    await user.upload(
      screen.getByLabelText('Choisir une photo'),
      imageFile('mini.png', 'image/png', 1024),
    )

    expect(
      await screen.findByText('La photo doit mesurer au moins 80 × 80 pixels.'),
    ).toBeInTheDocument()
  })
})

import { Link } from 'react-router'

export function NotFound() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
      <h1 className="text-3xl font-semibold">Page introuvable</h1>
      <p className="text-neutral-600 dark:text-neutral-300">
        La page que vous cherchez n’existe pas.
      </p>
      <Link to="/" className="text-brand-700 underline">
        Retour à l’accueil
      </Link>
    </main>
  )
}

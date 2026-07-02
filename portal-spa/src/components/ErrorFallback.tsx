/**
 * Écran de secours du Sentry.ErrorBoundary : une erreur de rendu ne doit jamais
 * laisser une page blanche. Accessible (role="alert", bouton natif) et en français.
 */
export function ErrorFallback() {
  return (
    <main
      role="alert"
      className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center"
    >
      <h1 className="text-2xl font-semibold">Une erreur est survenue</h1>
      <p className="max-w-md text-neutral-600 dark:text-neutral-300">
        Le portail a rencontré un problème inattendu. Rechargez la page ; si le problème persiste,
        contactez Ecoworking.
      </p>
      <button
        type="button"
        onClick={() => window.location.reload()}
        className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
      >
        Recharger la page
      </button>
    </main>
  )
}

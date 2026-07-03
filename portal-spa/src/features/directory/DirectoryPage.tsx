import { isAxiosError } from 'axios'
import { Linkedin, Search } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Spinner } from '@/components/ui/Spinner'
import { usePageTitle } from '@/lib/usePageTitle'
import { DirectoryTabs } from './DirectoryTabs'
import type { DirectoryEntry } from './types'
import { useDirectory } from './useDirectory'

/** Initiales pour l'avatar de repli (l'upload de photo n'existe pas encore). */
export function initials(entry: DirectoryEntry): string {
  return `${entry.first_name.charAt(0)}${entry.last_name.charAt(0)}`.toUpperCase()
}

/**
 * Annuaire des coworkers (PRD §3.7) : uniquement les membres opt-in, en
 * projection minimale (jamais d'email ni de téléphone). Recherche par nom,
 * entreprise ou poste.
 */
export function DirectoryPage() {
  usePageTitle('Annuaire — Portail Ecoworking')

  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const { data, isLoading, isError, error } = useDirectory(page, q)

  const forbidden = isAxiosError(error) && error.response?.status === 403

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="text-2xl font-semibold">Annuaire des coworkers</h1>
      <DirectoryTabs />

      <search aria-label="Recherche dans l’annuaire">
        <form
          className="flex items-end gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            setQ(searchInput.trim())
            setPage(1)
          }}
        >
          <div className="grow">
            <Label htmlFor="directory-search">Rechercher un coworker</Label>
            <Input
              id="directory-search"
              type="search"
              placeholder="Nom, entreprise ou poste…"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
            />
          </div>
          <Button type="submit" variant="secondary">
            <Search className="size-4" aria-hidden="true" />
            <span>Rechercher</span>
          </Button>
        </form>
      </search>

      {isLoading && <Spinner label="Chargement de l’annuaire…" />}
      {isError && (
        <Alert variant="error">
          {forbidden
            ? 'L’annuaire n’est pas accessible avec votre profil.'
            : 'Impossible de charger l’annuaire.'}
        </Alert>
      )}

      {data && data.data.length === 0 && (
        <Alert variant="info">
          {q === ''
            ? 'Aucun coworker ne s’affiche dans l’annuaire pour le moment.'
            : `Aucun coworker ne correspond à « ${q} ».`}
        </Alert>
      )}

      {data && data.data.length > 0 && (
        <>
          <p aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
            {data.meta.total} coworker{data.meta.total > 1 ? 's' : ''} dans l’annuaire
          </p>

          <ul className="grid gap-4 sm:grid-cols-2">
            {data.data.map((entry) => (
              <li
                key={entry.id}
                className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
              >
                <article aria-labelledby={`coworker-${entry.id}-name`} className="flex gap-3">
                  <span
                    aria-hidden="true"
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-50 text-base font-semibold text-brand-700 dark:bg-neutral-800 dark:text-brand-50"
                  >
                    {initials(entry)}
                  </span>
                  <div className="min-w-0 space-y-1">
                    <h2 id={`coworker-${entry.id}-name`} className="text-base font-medium">
                      {entry.first_name} {entry.last_name}
                    </h2>
                    {entry.company && <p className="text-sm">{entry.company}</p>}
                    {entry.job_title && (
                      <p className="text-sm text-neutral-600 dark:text-neutral-300">
                        {entry.job_title}
                      </p>
                    )}
                    {entry.bio && (
                      <p className="text-sm text-neutral-600 dark:text-neutral-300">{entry.bio}</p>
                    )}
                    <ul className="flex flex-wrap gap-3 pt-1">
                      {entry.linkedin_url && (
                        <li>
                          <a
                            href={entry.linkedin_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-sm text-brand-700 dark:text-brand-300 underline"
                          >
                            <Linkedin className="size-4" aria-hidden="true" />
                            LinkedIn
                            <span className="sr-only">
                              {' '}
                              de {entry.first_name} {entry.last_name} (nouvelle fenêtre)
                            </span>
                          </a>
                        </li>
                      )}
                      {entry.website_url && (
                        <li>
                          <a
                            href={entry.website_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm text-brand-700 dark:text-brand-300 underline"
                          >
                            Site web
                            <span className="sr-only">
                              {' '}
                              de {entry.first_name} {entry.last_name} (nouvelle fenêtre)
                            </span>
                          </a>
                        </li>
                      )}
                    </ul>
                  </div>
                </article>
              </li>
            ))}
          </ul>

          {data.meta.last_page > 1 && (
            <nav
              className="flex items-center justify-between"
              aria-label="Pagination de l’annuaire"
            >
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((value) => Math.max(1, value - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((value) => value + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}
    </div>
  )
}

import { Linkedin, Search, Users } from 'lucide-react'
import { useState } from 'react'
import { Avatar, initialsOf } from '@/components/Avatar'
import { EmptyState } from '@/components/EmptyState'
import { MarkdownContent } from '@/components/MarkdownContent'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { usePageTitle } from '@/lib/usePageTitle'
import { DirectoryTabs } from './DirectoryTabs'
import type { DirectoryEntry } from './types'
import { useDirectory } from './useDirectory'

/** Initiales pour l'avatar de repli (PRD §3.4.2 : photo absente). */
export function initials(entry: DirectoryEntry): string {
  return initialsOf(entry.first_name, entry.last_name)
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
  const { data, isLoading, isError, error, refetch } = useDirectory(page, q)

  return (
    <PageContainer width="full" className="space-y-6">
      <PageHeader title="Annuaire des coworkers" />
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
          <Button type="submit" variant="outline">
            <Search className="size-4" aria-hidden="true" />
            <span>Rechercher</span>
          </Button>
        </form>
      </search>

      {isLoading && (
        <div role="status" className="grid gap-4 sm:grid-cols-2">
          <span className="sr-only">Chargement de l’annuaire…</span>
          <Skeleton className="h-28 w-full" />
          <Skeleton className="h-28 w-full" />
        </div>
      )}
      {isError && (
        <QueryError
          error={error}
          fallback="Impossible de charger l’annuaire."
          onRetry={() => void refetch()}
        />
      )}

      {data && data.data.length === 0 && (
        <EmptyState
          icon={Users}
          title={
            q === ''
              ? 'Aucun coworker ne s’affiche dans l’annuaire pour le moment.'
              : `Aucun coworker ne correspond à « ${q} ».`
          }
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <p aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
            {data.meta.total} coworker{data.meta.total > 1 ? 's' : ''} dans l’annuaire
          </p>

          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {data.data.map((entry) => (
              <li key={entry.id}>
                <Card className="h-full">
                  <CardContent>
                    <article aria-labelledby={`coworker-${entry.id}-name`} className="flex gap-3">
                      <Avatar
                        firstName={entry.first_name}
                        lastName={entry.last_name}
                        photo={entry.photo}
                      />
                      <div className="min-w-0 space-y-1">
                        <h2 id={`coworker-${entry.id}-name`} className="text-base font-medium">
                          {entry.first_name} {entry.last_name}
                        </h2>
                        {entry.company && <p className="text-sm">{entry.company}</p>}
                        {entry.job_title && (
                          <p className="text-sm text-muted-foreground">{entry.job_title}</p>
                        )}
                        {entry.bio && (
                          <MarkdownContent
                            markdown={entry.bio}
                            className="text-sm text-muted-foreground"
                          />
                        )}
                        <ul className="flex flex-wrap gap-3 pt-1">
                          {entry.linkedin_url && (
                            <li>
                              <a
                                href={entry.linkedin_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-sm text-link underline"
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
                                className="text-sm text-link underline"
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
                  </CardContent>
                </Card>
              </li>
            ))}
          </ul>

          {data.meta.last_page > 1 && (
            <nav
              className="flex items-center justify-between"
              aria-label="Pagination de l’annuaire"
            >
              <Button
                variant="outline"
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
                variant="outline"
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
    </PageContainer>
  )
}

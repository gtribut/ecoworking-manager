import { useEffect, useState } from 'react'
import { cn } from '@/lib/utils'

/*
 * Coexiste volontairement avec `components/ui/avatar.tsx` (review U5,
 * CLAUDE.md §3.5) : ce composant porte une logique propre au portail que la
 * primitive Radix ne couvre pas — trois tailles liées à trois rendus serveur
 * distincts (`GET /api/users/{id}/photo/{size}`, jamais une seule image
 * redimensionnée en CSS), un état `muted` (résident absent sur le plan des
 * étages) et un repli initiales -> `onError` géré nous-mêmes (Radix
 * `Avatar.Fallback` ne se déclenche que sur `onLoadingStatusChange`, pas sur
 * une 404 après un premier affichage réussi). Utilisé par l'annuaire, le plan
 * des étages et l'aperçu de profil. `components/ui/avatar.tsx` (primitive
 * shadcn/ui) reste pour les avatars simples sans ces contraintes (menu
 * profil : initiales seules, pas de photo ni de tailles serveur).
 */

/** URLs des trois rendus servis par l'API (80 / 200 / 400 px), ou null. */
export interface PhotoUrls {
  sm: string
  md: string
  lg: string
}

type AvatarSize = 'sm' | 'md' | 'lg'

interface AvatarProps {
  firstName: string
  lastName: string
  photo?: PhotoUrls | null
  size?: AvatarSize
  /** Photo grisée : résident absent sur le plan des étages (PRD §3.7.3). */
  muted?: boolean
  className?: string
}

/** Boîte d'affichage par taille + rendu serveur à utiliser (jamais plus grand). */
const BOXES: Record<AvatarSize, { box: string; text: string; source: keyof PhotoUrls }> = {
  sm: { box: 'size-12', text: 'text-base', source: 'sm' },
  md: { box: 'size-20', text: 'text-xl', source: 'md' },
  lg: { box: 'size-32', text: 'text-3xl', source: 'lg' },
}

/** Initiales de repli (PRD §3.4.2 : avatar généré si pas de photo). */
export function initialsOf(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase()
}

/**
 * Avatar d'un coworker : photo de profil si elle existe, initiales sinon
 * (PRD §3.4.2). La photo n'est jamais une URL publique — elle passe par
 * `GET /api/users/{id}/photo/{size}`, authentifié et autorisé côté serveur.
 *
 * A11y : l'alternative de la photo est l'identité de la personne — pas
 * « Photo de … », préfixe redondant que les lecteurs d'écran annoncent déjà
 * (et que `noRedundantAlt` refuse, cf. Biome). Les initiales de repli sont
 * `aria-hidden` : le nom figure toujours à côté, en texte.
 */
export function Avatar({
  firstName,
  lastName,
  photo,
  size = 'sm',
  muted = false,
  className,
}: AvatarProps) {
  const { box, text, source } = BOXES[size]
  const [broken, setBroken] = useState(false)

  // Nouvelle photo (ou suppression) : on redonne sa chance au chargement.
  const src = photo?.[source] ?? null
  // biome-ignore lint/correctness/useExhaustiveDependencies: réinitialise l'état d'erreur quand la source change, pas à chaque rendu.
  useEffect(() => {
    setBroken(false)
  }, [src])

  if (photo && !broken) {
    return (
      // biome-ignore lint/a11y/noNoninteractiveElementInteractions: `onError` est un événement de cycle de vie du chargement de l'image (pas une interaction utilisateur) — aucun élément interactif natif ne s'y substitue pour un <img>.
      <img
        src={photo[source]}
        alt={`${firstName} ${lastName}`}
        loading="lazy"
        decoding="async"
        // Fichier absent ou non résoluble (photo héritée, purge de stockage) :
        // on retombe sur les initiales plutôt que sur une image cassée.
        onError={() => setBroken(true)}
        className={cn(
          box,
          'shrink-0 rounded-full object-cover',
          muted && 'opacity-50 grayscale',
          className,
        )}
      />
    )
  }

  return (
    <span
      aria-hidden="true"
      className={cn(
        box,
        text,
        'flex shrink-0 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-700 dark:bg-neutral-800 dark:text-brand-50',
        muted && 'opacity-50 grayscale',
        className,
      )}
    >
      {initialsOf(firstName, lastName)}
    </span>
  )
}

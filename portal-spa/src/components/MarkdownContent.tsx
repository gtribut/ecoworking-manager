import DOMPurify from 'dompurify'
import { marked } from 'marked'
import { useMemo } from 'react'
import { cn } from '@/lib/utils'

marked.setOptions({ gfm: true, breaks: true })

// Ouvre les liens dans un nouvel onglet, sans exposer `window.opener`
// (PRD §3.4.2 : rendu markdown « sécurisé »). Enregistré une seule fois au
// chargement du module — s'applique à tout `DOMPurify.sanitize()` de l'app.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank')
    node.setAttribute('rel', 'noopener noreferrer')
  }
})

/**
 * Allow-list stricte (CLAUDE.md §3.5, PRD §3.4.2) : mise en forme de texte
 * uniquement. Aucune image, aucun HTML brut (`<script>`, gestionnaires
 * d'évènements…) ne survit à `DOMPurify.sanitize`.
 */
const ALLOWED_TAGS = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'br', 'code']
const ALLOWED_ATTR = ['href']

export interface MarkdownContentProps {
  /** Source markdown brute (jamais du HTML : ni stockée ni rendue comme telle avant sanitize). */
  markdown: string
  className?: string
}

/**
 * Rendu markdown sécurisé (XSS-safe) de la présentation d'un membre — utilisé
 * par l'aperçu du profil et la fiche annuaire/plan (PRD §3.4.2, §3.7.4).
 * `marked` compile le markdown en HTML, `DOMPurify` le nettoie selon une
 * allow-list stricte avant injection dans le DOM.
 */
export function MarkdownContent({ markdown, className }: MarkdownContentProps) {
  const html = useMemo(() => {
    const rawHtml = marked.parse(markdown, { async: false })
    return DOMPurify.sanitize(rawHtml, { ALLOWED_TAGS, ALLOWED_ATTR })
  }, [markdown])

  return (
    <div
      // Puces/numéros visibles (review U5) : sans ça, `<ul>`/`<ol>` rendus par
      // `marked` n'ont ni marqueur ni retrait (pas de feuille de style Tailwind
      // par défaut sur les listes), illisibles visuellement bien que corrects
      // sémantiquement pour un lecteur d'écran.
      className={cn('[&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5', className)}
      // biome-ignore lint/security/noDangerouslySetInnerHtml: HTML sanitizé par DOMPurify (allow-list stricte) juste au-dessus.
      dangerouslySetInnerHTML={{ __html: html }}
    />
  )
}

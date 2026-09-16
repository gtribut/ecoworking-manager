import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
// Import Vite `?raw` (pas de lecture disque via `node:fs` : le tsconfig du
// portail n'a pas les types Node, cf. CLAUDE.md portail — pas de dépendance ajoutée).
import stylesCss from '@/styles.css?raw'
import { Tabs, TabsContent, TabsList, TabsTrigger } from './tabs'

// Note sur la couverture CSS-level (régression recette C14 : TabsList affichée
// verticalement à gauche) :
// - jsdom (via cssstyle/jsdom-css-parser) échoue à parser le CSS compilé par
//   Tailwind v4 (oklch, cascade layers @layer, imbrication) — un `getComputedStyle`
//   après `import '@/styles.css'` renvoie alors les valeurs par défaut du
//   navigateur (`display: block`) quel que soit l'état du correctif, ce qui
//   rend ce type d'assertion silencieusement toujours fausse (testé
//   manuellement : `jsdom` lève « Could not parse CSS stylesheet »).
// - Le test ci-dessous vérifie donc directement la **source** de la règle
//   corrigée (présence des `@custom-variant data-horizontal`/`data-vertical`
//   dans `styles.css`, sur le bon sélecteur `[data-orientation=...]`), ce
//   qui détecte une régression si ces lignes disparaissent.
// - Vérification visuelle réelle faite manuellement : `vite build` + rendu de
//   `/profile` (voir rapport de tâche) — la TabsList est bien une barre
//   horizontale au-dessus du contenu, en clair et en sombre.
describe('Tabs — orientation (régression recette C14 : liste verticale à gauche)', () => {
  it('est horizontal par défaut : data-orientation="horizontal" sur le root', () => {
    render(
      <Tabs defaultValue="profil">
        <TabsList>
          <TabsTrigger value="profil">Profil</TabsTrigger>
        </TabsList>
        <TabsContent value="profil">Contenu</TabsContent>
      </Tabs>,
    )

    expect(screen.getByRole('tablist').closest('[data-slot="tabs"]')).toHaveAttribute(
      'data-orientation',
      'horizontal',
    )
  })

  it("place la TabsList avant le contenu dans l'ordre du DOM", () => {
    render(
      <Tabs defaultValue="profil">
        <TabsList>
          <TabsTrigger value="profil">Profil</TabsTrigger>
        </TabsList>
        <TabsContent value="profil">Contenu de l’onglet</TabsContent>
      </Tabs>,
    )

    const root = screen.getByRole('tablist').closest('[data-slot="tabs"]') as HTMLElement
    const children = Array.from(root.children)
    const listIndex = children.indexOf(screen.getByRole('tablist'))
    const contentIndex = children.findIndex((child) =>
      child.textContent?.includes('Contenu de l’onglet'),
    )

    expect(listIndex).toBeGreaterThanOrEqual(0)
    expect(listIndex).toBeLessThan(contentIndex)
  })

  it('déclare les variantes `data-horizontal`/`data-vertical` sur `[data-orientation]` dans styles.css', () => {
    // Sans ces déclarations, les classes `data-horizontal:flex-col` etc. des
    // primitives (tabs, separator, scroll-area, toggle-group) ne sont jamais
    // générées par Tailwind : c'était le bug de recette (TabsList empilée à
    // gauche au lieu d'une barre horizontale au-dessus).
    expect(stylesCss).toMatch(
      /@custom-variant data-horizontal \(&\[data-orientation="horizontal"\]\);/,
    )
    expect(stylesCss).toMatch(/@custom-variant data-vertical \(&\[data-orientation="vertical"\]\);/)
  })
})

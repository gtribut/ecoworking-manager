import { cva, type VariantProps } from 'class-variance-authority'
import { Slot } from 'radix-ui'
import type * as React from 'react'
import { cn } from '@/lib/utils'

/*
 * Primitive shadcn/ui (style radix-nova), avec trois écarts assumés par rapport
 * au fichier généré — cf. ADR-0013 D1 et les maquettes C14 :
 *
 * 1. Échelle de tailles alignée sur les maquettes (bouton 36 px, rayon 8 px) :
 *    nova propose h-8 / h-7 / h-9, on garde h-9 / h-8 / h-10 pour `default` /
 *    `sm` / `lg`. `sm` reste ainsi à 32 px comme le kit maison remplacé.
 * 2. Survol des variantes pleines via `--primary-hover` / `--destructive-hover`
 *    (= brand-700 / rouge foncé) plutôt que `bg-primary/80` : à 80 % d'opacité
 *    le contraste du libellé blanc tombe sous 4.5:1 (CLAUDE.md §3.5).
 * 3. `destructive` reste un bouton plein rouge (nova le rend en pastille
 *    translucide) : même affordance que le `variant="danger"` du kit maison sur
 *    les actions de suppression, et 4.76:1 de contraste.
 *
 * Le focus utilise l'outline brand décollé, comme le reste du portail
 * (`:focus-visible` global dans styles.css), et non l'anneau translucide de
 * nova, illisible sur un fond vert plein.
 *
 * 4. `link` utilise l'utilitaire `text-link` (styles.css) plutôt que
 *    `text-primary` : sur une carte en thème sombre, `text-primary` (vert
 *    plein) ne tombe qu'à 3,77:1 (review U5, CLAUDE.md §3.5) — `text-link`
 *    est le même brand-700/brand-300 vérifié AA que tous les liens du
 *    portail.
 */
const buttonVariants = cva(
  "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary-hover',
        outline:
          'border-border bg-background hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50',
        secondary:
          'bg-secondary text-secondary-foreground hover:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)] aria-expanded:bg-secondary aria-expanded:text-secondary-foreground',
        ghost:
          'hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:hover:bg-muted/50',
        destructive:
          'bg-destructive text-destructive-foreground hover:bg-destructive-hover focus-visible:outline-destructive',
        link: 'text-link underline-offset-4 hover:underline',
      },
      size: {
        default:
          'h-9 gap-1.5 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
        xs: "h-7 gap-1 rounded-[min(var(--radius-md),10px)] px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
        sm: "h-8 gap-1 rounded-[min(var(--radius-md),12px)] px-3 text-[0.8rem] in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2 [&_svg:not([class*='size-'])]:size-3.5",
        lg: 'h-10 gap-1.5 px-6 has-data-[icon=inline-end]:pr-4 has-data-[icon=inline-start]:pl-4',
        icon: 'size-9',
        'icon-xs':
          "size-7 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3",
        'icon-sm':
          'size-8 rounded-[min(var(--radius-md),12px)] in-data-[slot=button-group]:rounded-lg',
        'icon-lg': 'size-10',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

export type ButtonProps = React.ComponentProps<'button'> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }

function Button({
  className,
  variant = 'default',
  size = 'default',
  asChild = false,
  type,
  ...props
}: ButtonProps) {
  const Comp = asChild ? Slot.Root : 'button'

  return (
    <Comp
      data-slot="button"
      data-variant={variant}
      data-size={size}
      // `type="button"` par défaut (comportement du kit maison remplacé) : sans
      // ça un bouton posé dans un <form> soumet le formulaire par accident.
      // Ignoré en mode `asChild`, où l'enfant porte sa propre sémantique.
      type={asChild ? type : (type ?? 'button')}
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }

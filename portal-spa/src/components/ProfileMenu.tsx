import { ChevronsUpDown, LogOut, User } from 'lucide-react'
import { Link } from 'react-router'
import { initialsOf } from '@/components/Avatar'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/features/auth/useAuth'
import { THEME_OPTIONS, type Theme, useThemeSelection } from '@/features/profile/useThemeSelection'
import { cn } from '@/lib/utils'

/** Valeur du groupe radio : `null` (thème système) n'est pas une valeur DOM. */
const SYSTEM_VALUE = 'system'

function toRadioValue(theme: Theme): string {
  return theme ?? SYSTEM_VALUE
}

function fromRadioValue(value: string): Theme {
  return value === SYSTEM_VALUE ? null : (value as Theme)
}

interface ProfileMenuProps {
  /**
   * `sidebar` : bloc pleine largeur en bas de la sidebar (avatar + nom +
   * identifiant + chevrons), maquette C14. `compact` : avatar seul, utilisé
   * dans la barre mobile de 56 px.
   */
  variant?: 'sidebar' | 'compact'
}

/**
 * Menu profil (PRD §3.1/§3.9) : « Mon profil », choix du thème et
 * « Déconnexion ». Depuis C14 (U2) il vit dans le bloc profil du bas de
 * sidebar — et dans la barre du haut en mobile — et s'appuie sur le
 * `DropdownMenu` shadcn/ui (Radix) plutôt que sur le menu APG maison :
 * mêmes rôles ARIA (`menu`, `menuitem`, `menuitemradio`), navigation flèches
 * / Home / End / Échap et retour du focus au déclencheur fournis par Radix.
 *
 * Attention en test : le panneau est rendu dans un **portail**, hors du DOM du
 * déclencheur, et Radix donne le focus au panneau (pas au premier élément) à
 * l'ouverture à la souris — une flèche bas suffit à entrer dans la liste.
 */
export function ProfileMenu({ variant = 'sidebar' }: ProfileMenuProps) {
  const { user, logout } = useAuth()
  const { theme, selectTheme } = useThemeSelection()

  if (!user) return null

  const fullName = `${user.first_name} ${user.last_name}`
  const initials = initialsOf(user.first_name, user.last_name)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          // Le nom et l'identifiant sont masqués en mode icône et en mobile :
          // sans ce libellé, le bouton perdrait son nom accessible.
          aria-label={`Menu profil de ${fullName}`}
          className={cn(
            'flex items-center gap-2.5 rounded-lg text-left transition-colors',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            variant === 'sidebar'
              ? 'w-full border border-sidebar-border p-2 hover:bg-sidebar-accent group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:p-1'
              : 'p-0.5 hover:bg-muted',
          )}
        >
          <Avatar className="size-8">
            <AvatarFallback className="bg-brand-50 text-xs font-semibold text-link dark:bg-neutral-800">
              {initials}
            </AvatarFallback>
          </Avatar>
          {variant === 'sidebar' && (
            <>
              <span className="flex min-w-0 flex-1 flex-col group-data-[collapsible=icon]:hidden">
                <span className="truncate text-[13px] font-semibold">{fullName}</span>
                {/* `GET /api/user` n'expose pas l'entité de rattachement : on
                    affiche l'email, seul identifiant secondaire disponible
                    (point ouvert U2, cf. rapport de lot). */}
                <span className="truncate text-xs text-muted-foreground">{user.email}</span>
              </span>
              <ChevronsUpDown
                className="size-4 shrink-0 text-muted-foreground group-data-[collapsible=icon]:hidden"
                aria-hidden="true"
              />
            </>
          )}
        </button>
      </DropdownMenuTrigger>

      {/* Radix nomme le panneau d'après son déclencheur (`aria-labelledby`) :
          « Menu profil de … ». Pas d'`aria-label` en plus, il serait ignoré. */}
      <DropdownMenuContent
        align={variant === 'sidebar' ? 'start' : 'end'}
        side={variant === 'sidebar' ? 'top' : 'bottom'}
        className="w-60"
      >
        <DropdownMenuItem asChild>
          <Link to="/profile">
            <User aria-hidden="true" />
            Mon profil
          </Link>
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuLabel>Thème</DropdownMenuLabel>
        <DropdownMenuRadioGroup
          value={toRadioValue(theme)}
          onValueChange={(value) => selectTheme(fromRadioValue(value))}
        >
          {THEME_OPTIONS.map((option) => (
            <DropdownMenuRadioItem key={option.label} value={toRadioValue(option.value)}>
              <option.icon aria-hidden="true" />
              {option.label}
            </DropdownMenuRadioItem>
          ))}
        </DropdownMenuRadioGroup>

        <DropdownMenuSeparator />

        <DropdownMenuItem onSelect={() => void logout()}>
          <LogOut aria-hidden="true" />
          Déconnexion
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

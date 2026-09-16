import { Link, NavLink, useLocation } from 'react-router'
import { BrandMark } from '@/components/BrandMark'
import { ProfileMenu } from '@/components/ProfileMenu'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { isEntryActive, type NavEntry, useNavEntries } from '@/components/useNavEntries'

/** Entrée active : vert brand en clair, brand-300 sur gris foncé en sombre. */
const MENU_BUTTON_CLASS =
  'h-10 gap-3 px-3 font-medium text-neutral-600 data-active:bg-brand-50 data-active:font-semibold data-active:text-brand-700 dark:text-neutral-300 dark:data-active:bg-neutral-800 dark:data-active:text-brand-300 [&_svg]:size-[18px]'

function NavItem({ entry, pathname }: { entry: NavEntry; pathname: string }) {
  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        asChild
        isActive={isEntryActive(entry, pathname)}
        tooltip={entry.label}
        className={MENU_BUTTON_CLASS}
      >
        {/* `NavLink` pose `aria-current="page"` ; l'état visuel passe par
            `isActive` ci-dessus (le bouton shadcn ne lit pas le rendu du lien). */}
        <NavLink to={entry.to} end={entry.end}>
          <entry.icon aria-hidden="true" />
          <span>{entry.label}</span>
        </NavLink>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

/**
 * Sidebar du portail (PRD §3.9.1, maquettes C14) : 256 px, rétractable en mode
 * icône (état persisté par la primitive), groupe principal + groupe
 * « Administratif », bloc profil en pied. Masquée sous `md`, où la navigation
 * passe par la bottom nav.
 */
export function AppSidebar() {
  const { main, admin } = useNavEntries()
  const { pathname } = useLocation()

  return (
    <Sidebar collapsible="icon" className="border-sidebar-border">
      <SidebarHeader className="h-14 justify-center px-3">
        {/* `Link` et non `NavLink` : sur l'accueil, `NavLink` poserait un second
            `aria-current="page"` en plus de l'entrée « Accueil » de la nav.
            Le libellé est masqué en mode icône, d'où le nom accessible porté
            par le lien lui-même (axe `link-name`). */}
        <Link
          to="/"
          aria-label="Ecoworking — accueil"
          className="flex items-center gap-2.5 rounded-lg group-data-[collapsible=icon]:justify-center"
        >
          <BrandMark />
          <span
            aria-hidden="true"
            className="truncate text-base font-bold tracking-tight group-data-[collapsible=icon]:hidden"
          >
            Ecoworking
          </span>
        </Link>
      </SidebarHeader>

      <SidebarContent>
        <nav aria-label="Navigation principale">
          <SidebarGroup>
            <SidebarGroupContent>
              <SidebarMenu className="gap-0.5">
                {main.map((entry) => (
                  <NavItem key={entry.to} entry={entry} pathname={pathname} />
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>

          {admin.length > 0 && (
            <SidebarGroup>
              <SidebarGroupLabel className="px-3 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                Administratif
              </SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu className="gap-0.5">
                  {admin.map((entry) => (
                    <NavItem key={entry.to} entry={entry} pathname={pathname} />
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          )}
        </nav>
      </SidebarContent>

      <SidebarFooter className="p-3">
        <ProfileMenu />
      </SidebarFooter>
    </Sidebar>
  )
}

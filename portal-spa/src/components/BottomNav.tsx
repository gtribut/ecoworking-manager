import { LogOut, MoreHorizontal, UserCircle } from 'lucide-react'
import { useState } from 'react'
import { NavLink } from 'react-router'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { type NavEntry, useNavEntries } from '@/components/useNavEntries'
import { useAuth } from '@/features/auth/useAuth'
import { cn } from '@/lib/utils'

/** Onglets attendus par le PRD (§3.9.2), dans l'ordre. */
const PREFERRED_TABS = ['/', '/bookings', '/presence', '/announcements'] as const

/**
 * Quatre onglets fixes + « Plus ». Un onglet dont le module n'est pas autorisé
 * pour le rôle est **remplacé à sa place** par le premier module autorisé qui
 * n'est pas déjà prévu ailleurs dans la barre, pris dans l'ordre de la
 * navigation principale : un external voit ainsi « Tickets » à la place de
 * « Présence », un contact facturation « Documents » puis « Factures ».
 */
export function bottomNavTabs(all: NavEntry[]): NavEntry[] {
  const spares = all.filter((entry) => !PREFERRED_TABS.some((to) => to === entry.to))
  const tabs: NavEntry[] = []
  let spareIndex = 0

  for (const to of PREFERRED_TABS) {
    const entry = all.find((candidate) => candidate.to === to)
    if (entry !== undefined) {
      tabs.push(entry)
      continue
    }
    const spare = spares[spareIndex]
    if (spare !== undefined) {
      spareIndex += 1
      tabs.push(spare)
    }
  }

  return tabs
}

const TAB_CLASS =
  'flex h-14 flex-col items-center justify-center gap-1 text-[11px] leading-none font-medium'

/**
 * Navigation mobile (PRD §3.9.2, maquettes C14) : barre basse de 5 onglets
 * sous `md`, cibles de 56 px, safe-area iOS respectée. « Plus » ouvre un Sheet
 * listant les modules restants, « Mon profil » et la déconnexion.
 */
export function BottomNav() {
  const { all } = useNavEntries()
  const { logout } = useAuth()
  const [open, setOpen] = useState(false)

  const tabs = bottomNavTabs(all)
  const rest = all.filter((entry) => !tabs.includes(entry))

  return (
    <>
      <nav
        aria-label="Navigation rapide"
        className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
      >
        <ul className="flex items-stretch">
          {tabs.map((entry) => (
            <li key={entry.to} className="flex-1">
              <NavLink
                to={entry.to}
                end={entry.end}
                className={({ isActive }) =>
                  cn(
                    TAB_CLASS,
                    isActive
                      ? 'font-semibold text-brand-700 dark:text-brand-300'
                      : 'text-muted-foreground',
                  )
                }
              >
                <entry.icon className="size-[22px]" aria-hidden="true" />
                {entry.label}
              </NavLink>
            </li>
          ))}
          <li className="flex-1">
            <button
              type="button"
              aria-haspopup="dialog"
              aria-expanded={open}
              onClick={() => setOpen(true)}
              className={cn(TAB_CLASS, 'w-full text-muted-foreground')}
            >
              <MoreHorizontal className="size-[22px]" aria-hidden="true" />
              Plus
            </button>
          </li>
        </ul>
      </nav>

      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="bottom" className="max-h-[80svh] overflow-y-auto pb-6">
          <SheetHeader>
            <SheetTitle>Plus</SheetTitle>
            <SheetDescription>Vos autres modules et votre compte.</SheetDescription>
          </SheetHeader>

          <nav aria-label="Modules supplémentaires" className="px-2">
            <ul className="flex flex-col">
              {rest.map((entry) => (
                <li key={entry.to}>
                  <NavLink
                    to={entry.to}
                    end={entry.end}
                    onClick={() => setOpen(false)}
                    className={({ isActive }) =>
                      cn(
                        'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
                        isActive ? 'text-brand-700 dark:text-brand-300' : 'text-foreground',
                      )
                    }
                  >
                    <entry.icon className="size-[18px]" aria-hidden="true" />
                    {entry.label}
                  </NavLink>
                </li>
              ))}
              <li>
                <NavLink
                  to="/profile"
                  onClick={() => setOpen(false)}
                  className={({ isActive }) =>
                    cn(
                      'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
                      isActive ? 'text-brand-700 dark:text-brand-300' : 'text-foreground',
                    )
                  }
                >
                  <UserCircle className="size-[18px]" aria-hidden="true" />
                  Mon profil
                </NavLink>
              </li>
            </ul>
          </nav>

          <div className="px-4">
            <Button
              variant="outline"
              className="w-full"
              onClick={() => {
                setOpen(false)
                void logout()
              }}
            >
              <LogOut aria-hidden="true" />
              Déconnexion
            </Button>
          </div>
        </SheetContent>
      </Sheet>
    </>
  )
}

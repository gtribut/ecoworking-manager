import { LogOut, Monitor, Moon, Sun, User } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { toast } from 'sonner'
import { useAuth } from '@/features/auth/useAuth'
import { useUpdateTheme } from '@/features/profile/useProfile'
import { getApiErrorMessage } from '@/lib/errors'
import { cn } from '@/lib/utils'

type Theme = 'light' | 'dark' | null

const THEME_OPTIONS: { value: Theme; label: string; icon: typeof Sun }[] = [
  { value: 'light', label: 'Clair', icon: Sun },
  { value: 'dark', label: 'Sombre', icon: Moon },
  { value: null, label: 'Système', icon: Monitor },
]

/** Applique la classe `dark` sur `<html>` sans attendre la réponse serveur (PRD §3.1). */
function applyThemeClass(theme: Theme): void {
  const root = document.documentElement
  if (theme === null) {
    root.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches)
    return
  }
  root.classList.toggle('dark', theme === 'dark')
}

function initials(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase()
}

/**
 * Menu profil du header (PRD §3.1/§3.9, lot G) : avatar (initiales) + nom,
 * contenant « Mon profil », le switch de thème et « Déconnexion » — qui migre
 * ici depuis le bouton direct du header (acté PRD §3.2). Pattern APG « menu
 * button » : `role="menu"`/`role="menuitem"`, flèches, Home/End, Échap, et
 * focus rendu au déclencheur à la fermeture.
 */
export function ProfileMenu() {
  const { user, logout } = useAuth()
  const updateTheme = useUpdateTheme()

  const [open, setOpen] = useState(false)
  const [optimisticTheme, setOptimisticTheme] = useState<Theme>(user?.theme ?? null)
  const [activeIndex, setActiveIndex] = useState(0)

  const containerRef = useRef<HTMLDivElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const itemRefs = useRef<(HTMLElement | null)[]>([])

  useEffect(() => {
    setOptimisticTheme(user?.theme ?? null)
  }, [user?.theme])

  // Nombre d'éléments focusables du menu : « Mon profil », 3 thèmes, « Déconnexion ».
  const itemCount = 1 + THEME_OPTIONS.length + 1

  useEffect(() => {
    if (open) {
      itemRefs.current[0]?.focus()
      setActiveIndex(0)
    }
  }, [open])

  useEffect(() => {
    if (!open) return

    function onClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [open])

  function focusIndex(index: number) {
    const next = (index + itemCount) % itemCount
    setActiveIndex(next)
    itemRefs.current[next]?.focus()
  }

  function close() {
    setOpen(false)
    buttonRef.current?.focus()
  }

  function onMenuKeyDown(event: React.KeyboardEvent) {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        focusIndex(activeIndex + 1)
        break
      case 'ArrowUp':
        event.preventDefault()
        focusIndex(activeIndex - 1)
        break
      case 'Home':
        event.preventDefault()
        focusIndex(0)
        break
      case 'End':
        event.preventDefault()
        focusIndex(itemCount - 1)
        break
      case 'Escape':
        event.preventDefault()
        close()
        break
      case 'Tab':
        // Sans preventDefault, le Tab natif calcule le prochain élément
        // focusable AVANT que React ne démonte le menu (fermé par
        // `setOpen(false)`) : l'élément de référence disparaît pendant le
        // calcul et le focus retombe sur <body>, silencieusement (review).
        // On ferme nous-mêmes (focus rendu au bouton déclencheur) et on
        // laisse un Tab *suivant* repartir de là.
        event.preventDefault()
        close()
        break
      default:
        break
    }
  }

  function selectTheme(theme: Theme) {
    const previous = optimisticTheme
    applyThemeClass(theme)
    setOptimisticTheme(theme)
    updateTheme.mutate(theme, {
      onError: (error) => {
        // Pas de rollback → l'UI reste en thème sombre alors que le serveur
        // est resté en clair, sans qu'aucun message ne le signale (review).
        applyThemeClass(previous)
        setOptimisticTheme(previous)
        toast.error(getApiErrorMessage(error, 'Impossible d’enregistrer le thème.'))
      },
    })
  }

  if (!user) return null

  let index = -1
  const nextIndex = () => {
    index += 1
    return index
  }

  return (
    <div ref={containerRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        // Sous 640 px le nom est masqué (`hidden sm:inline`) et les initiales
        // sont `aria-hidden` : sans ceci, le bouton n'a plus de nom accessible
        // en mobile (review).
        aria-label={`Menu profil de ${user.first_name} ${user.last_name}`}
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
      >
        <span
          aria-hidden="true"
          className="flex size-7 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white"
        >
          {initials(user.first_name, user.last_name)}
        </span>
        <span className="hidden sm:inline">
          {user.first_name} {user.last_name}
        </span>
      </button>

      {open && (
        <div
          role="menu"
          aria-label="Menu profil"
          onKeyDown={onMenuKeyDown}
          className="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-lg border border-neutral-200 bg-white py-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
        >
          <Link
            ref={(el) => {
              itemRefs.current[nextIndex()] = el
            }}
            to="/profile"
            role="menuitem"
            tabIndex={-1}
            onClick={close}
            className="flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
          >
            <User className="size-4" aria-hidden="true" />
            Mon profil
          </Link>

          <fieldset className="border-t border-neutral-100 px-4 py-2 dark:border-neutral-800">
            <legend className="mb-1 text-xs font-medium text-neutral-500 dark:text-neutral-400">
              Thème
            </legend>
            <div className="flex gap-1">
              {THEME_OPTIONS.map((option) => {
                const Icon = option.icon
                const checked = optimisticTheme === option.value
                return (
                  <button
                    key={option.label}
                    ref={(el) => {
                      itemRefs.current[nextIndex()] = el
                    }}
                    type="button"
                    role="menuitemradio"
                    aria-checked={checked}
                    tabIndex={-1}
                    onClick={() => selectTheme(option.value)}
                    className={cn(
                      'flex flex-1 flex-col items-center gap-1 rounded-md px-2 py-1.5 text-xs',
                      checked
                        ? 'bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-50'
                        : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
                    )}
                  >
                    <Icon className="size-4" aria-hidden="true" />
                    {option.label}
                  </button>
                )
              })}
            </div>
          </fieldset>

          <button
            ref={(el) => {
              itemRefs.current[nextIndex()] = el
            }}
            type="button"
            role="menuitem"
            tabIndex={-1}
            onClick={() => {
              setOpen(false)
              void logout()
            }}
            className="flex w-full items-center gap-2 border-t border-neutral-100 px-4 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-800"
          >
            <LogOut className="size-4" aria-hidden="true" />
            Déconnexion
          </button>
        </div>
      )}
    </div>
  )
}

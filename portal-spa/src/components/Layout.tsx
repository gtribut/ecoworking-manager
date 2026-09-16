import { useEffect, useMemo, useRef, useState } from 'react'
import { Outlet, useLocation } from 'react-router'
import { Toaster } from 'sonner'
import { AppSidebar } from '@/components/AppSidebar'
import { BottomNav } from '@/components/BottomNav'
import { PageHeaderSlotContext } from '@/components/PageHeader'
import { TopBar } from '@/components/TopBar'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { TOAST_CLASS_NAMES, TOAST_CONTAINER_ARIA_LABEL } from '@/lib/toastTheme'
import { useIsDarkMode } from '@/lib/useIsDarkMode'
import { Footer } from './Footer'
import { OfflineBanner } from './OfflineBanner'

/**
 * Shell du portail membre (PRD §3.9, ré-actage C14 / ADR-0013) : sidebar
 * rétractable en desktop, top bar de 60 px portant le titre de la page
 * (`PageHeader`) et les actions globales, bottom nav de 5 onglets sous `md`.
 *
 * Structure : la top bar et le contenu vivent dans le `<main>` — le titre de
 * page reste ainsi l'unique `<h1>` du contenu principal (RGAA 9.1) — tandis
 * que le pied de page reste à l'extérieur pour conserver son rôle
 * `contentinfo`.
 */
export function Layout() {
  const isDark = useIsDarkMode()
  const location = useLocation()
  const mainRef = useRef<HTMLElement>(null)
  const previousPathname = useRef<string | null>(null)

  // Emplacement du titre de page dans la top bar, alimenté par `PageHeader`.
  const [titleSlot, setTitleSlot] = useState<HTMLElement | null>(null)
  const slot = useMemo(() => ({ element: titleSlot }), [titleSlot])

  // Gestion du focus au changement de route (RGAA 12.x) : replace le focus sur
  // le contenu principal pour que les lecteurs d'écran annoncent la nouvelle page.
  useEffect(() => {
    if (previousPathname.current !== null && previousPathname.current !== location.pathname) {
      mainRef.current?.focus()
    }
    previousPathname.current = location.pathname
  }, [location.pathname])

  return (
    <PageHeaderSlotContext.Provider value={slot}>
      <SidebarProvider>
        <a href="#main-content" className="skip-link">
          Aller au contenu principal
        </a>

        {/* Toasts (PRD §3.1/§3.8.4) : feedback d'action éphémère, thème suivi,
            animations coupées si prefers-reduced-motion (géré par Sonner). */}
        <Toaster
          closeButton
          position="top-right"
          theme={isDark ? 'dark' : 'light'}
          containerAriaLabel={TOAST_CONTAINER_ARIA_LABEL}
          toastOptions={{ classNames: TOAST_CLASS_NAMES }}
        />

        <AppSidebar />

        <SidebarInset asChild className="bg-neutral-50 dark:bg-neutral-950">
          <div>
            <OfflineBanner />
            <main
              id="main-content"
              ref={mainRef}
              tabIndex={-1}
              className="flex flex-1 flex-col focus:outline-none"
            >
              <TopBar slotRef={setTitleSlot} />
              <Outlet />
            </main>
            {/* Marge basse en mobile : la bottom nav flotte au-dessus. */}
            <Footer className="pb-20 md:pb-0" />
          </div>
        </SidebarInset>

        <BottomNav />
      </SidebarProvider>
    </PageHeaderSlotContext.Provider>
  )
}

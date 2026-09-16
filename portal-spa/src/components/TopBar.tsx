import { Mail } from 'lucide-react'
import { BrandMark } from '@/components/BrandMark'
import { ProfileMenu } from '@/components/ProfileMenu'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button } from '@/components/ui/button'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { CONTACT_MAILTO } from '@/lib/contact'

/**
 * Barre supérieure du shell (PRD §3.9, ré-actage C14) : 60 px en desktop,
 * 56 px en mobile. À gauche, le repli de la sidebar et l'emplacement du titre
 * de page (`PageHeader`) en desktop, le logo en mobile ; à droite les actions
 * globales — contact, thème, notifications, et le menu profil en mobile (il
 * vit dans le pied de sidebar en desktop).
 */
export function TopBar({ slotRef }: { slotRef: (element: HTMLDivElement | null) => void }) {
  return (
    <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-card px-4 md:h-15 md:gap-3 md:px-8">
      <SidebarTrigger className="hidden md:inline-flex" />

      <div className="flex items-center gap-2.5 md:hidden">
        <BrandMark className="size-7" />
        <span className="text-base font-bold tracking-tight">Ecoworking</span>
      </div>

      {/* Cible du portail de `PageHeader` : le titre de la page courante. */}
      <div ref={slotRef} className="hidden min-w-0 flex-1 md:flex md:items-center" />

      <div className="ml-auto flex items-center gap-1 md:gap-2">
        <Button asChild variant="outline" size="sm" className="hidden md:inline-flex">
          <a href={CONTACT_MAILTO}>
            <Mail aria-hidden="true" />
            Nous contacter
          </a>
        </Button>
        <ThemeToggle />
        <NotificationBell />
        <div className="md:hidden">
          <ProfileMenu variant="compact" />
        </div>
      </div>
    </header>
  )
}

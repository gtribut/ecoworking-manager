import { Monitor } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { THEME_OPTIONS, type Theme, useThemeSelection } from '@/features/profile/useThemeSelection'

const SYSTEM_VALUE = 'system'

function toRadioValue(theme: Theme): string {
  return theme ?? SYSTEM_VALUE
}

function fromRadioValue(value: string): Theme {
  return value === SYSTEM_VALUE ? null : (value as Theme)
}

/**
 * Bouton de thème de la top bar (maquettes C14) : même préférence que le menu
 * profil (`useThemeSelection`, optimiste puis persistée), exposée ici en accès
 * direct. L'icône reflète le choix courant et le nom accessible l'annonce.
 */
export function ThemeToggle() {
  const { theme, selectTheme } = useThemeSelection()
  const current = THEME_OPTIONS.find((option) => option.value === theme)
  const Icon = current?.icon ?? Monitor

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon-sm"
          aria-label={`Thème : ${current?.label ?? 'Système'}`}
        >
          <Icon aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      {/* Nommé par le déclencheur (« Thème : … ») via `aria-labelledby` Radix. */}
      <DropdownMenuContent align="end" className="w-44">
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
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

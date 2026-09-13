/**
 * Couleurs de toast AA (fix review Playwright, lot G) : `richColors` de Sonner
 * échoue le contraste texte (ex. succès #008a2e sur #ecfdf3 ≈ 4,25:1, sous les
 * 4,5:1 requis en texte normal 13px). On reprend les tokens déjà validés
 * d'`ui/Alert` (succès/erreur) + un « info » assorti, dont le ratio texte/fond
 * dépasse largement 4,5:1 dans les deux thèmes :
 * - clair  : green-800/green-50 ≈ 6,8:1 · red-800/red-50 ≈ 7,6:1 · blue-800/blue-50 ≈ 8,0:1
 * - sombre : green-200/green-950 ≈ 12,3:1 · red-200/red-950 ≈ 13,2:1 · blue-200/blue-950 ≈ 12,0:1
 * L'icône Sonner est en `fill="currentColor"` : elle hérite du même texte, donc
 * du même ratio (≥ 3:1 largement couvert). Bordures en -400/-700 (renfort
 * volontaire par rapport aux -300/-800 d'Alert, non testées par la règle axe
 * `color-contrast` qui ne porte que sur le texte — un contrôle non-textuel
 * complet reste à faire à l'audit Pa11y de CLAUDE.md §3.5).
 */
export const TOAST_CLASS_NAMES = {
  success:
    'border-green-400 bg-green-50 text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-200',
  error:
    'border-red-400 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200',
  info: 'border-blue-400 bg-blue-50 text-blue-800 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200',
  warning:
    'border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200',
}

/** Nom de la région Sonner (fix review Playwright, lot G) : distinct de
 * `aria-label="Notifications"` de la cloche (deux régions "Notifications"
 * résolvaient en double dans `getByRole('region', { name: 'Notifications' })`). */
export const TOAST_CONTAINER_ARIA_LABEL = 'Messages de confirmation'

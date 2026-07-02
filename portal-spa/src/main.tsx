import * as Sentry from '@sentry/react'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import { App } from './App'
import { ErrorFallback } from './components/ErrorFallback'
import { AuthProvider } from './features/auth/AuthContext'
import { queryClient } from './lib/queryClient'
import { initSentry } from './lib/sentry'
import './styles.css'

initSentry()

const rootElement = document.getElementById('root')
if (!rootElement) {
  throw new Error('Élément racine #root introuvable.')
}

createRoot(rootElement).render(
  <StrictMode>
    {/* Filet de sécurité : une erreur de rendu affiche un écran de secours
        accessible (et remonte à Sentry si configuré) au lieu d'une page blanche. */}
    <Sentry.ErrorBoundary fallback={<ErrorFallback />}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AuthProvider>
            <App />
          </AuthProvider>
        </BrowserRouter>
        <ReactQueryDevtools initialIsOpen={false} />
      </QueryClientProvider>
    </Sentry.ErrorBoundary>
  </StrictMode>,
)

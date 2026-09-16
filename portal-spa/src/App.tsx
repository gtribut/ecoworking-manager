import { lazy, type ReactNode, Suspense } from 'react'
import { Route, Routes } from 'react-router'
import { Layout } from '@/components/Layout'
import { NotFound } from '@/components/NotFound'
import { Spinner } from '@/components/ui/spinner'
import { AnnouncementDetailPage } from '@/features/announcements/AnnouncementDetailPage'
import { AnnouncementsPage } from '@/features/announcements/AnnouncementsPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAccess } from '@/features/auth/RequireAccess'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { DocumentsPage } from '@/features/documents/DocumentsPage'
import { AccessibilitePage } from '@/features/legal/AccessibilitePage'
import { CguPage } from '@/features/legal/CguPage'
import { MentionsLegalesPage } from '@/features/legal/MentionsLegalesPage'
import { PresencePage } from '@/features/presence/PresencePage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { TicketsPage } from '@/features/tickets/TicketsPage'

/**
 * Code-splitting (PRD §3.8.1, lot G) : les trois modules les plus lourds
 * (calendrier de résa, annuaire + plan SVG, factures) partent dans leur
 * propre chunk, chargé à la navigation plutôt qu'au chargement initial.
 */
const BookingsPage = lazy(() =>
  import('@/features/bookings/BookingsPage').then((m) => ({ default: m.BookingsPage })),
)
const DirectoryPage = lazy(() =>
  import('@/features/directory/DirectoryPage').then((m) => ({ default: m.DirectoryPage })),
)
const FloorPlanPage = lazy(() =>
  import('@/features/directory/FloorPlanPage').then((m) => ({ default: m.FloorPlanPage })),
)
const InvoicesPage = lazy(() =>
  import('@/features/invoices/InvoicesPage').then((m) => ({ default: m.InvoicesPage })),
)

function PageFallback() {
  return (
    <div className="flex justify-center py-12">
      <Spinner label="Chargement de la page…" />
    </div>
  )
}

function Lazy({ children }: { children: ReactNode }) {
  return <Suspense fallback={<PageFallback />}>{children}</Suspense>
}

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      {/* R-03 — cible du lien « mot de passe oublié » (PRD §3.2), hors auth */}
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />

      {/* Pages légales (PRD §3.1/§3.9, lot G) : accessibles avec ou sans
          session, layout public minimal — jamais sous <RequireAuth>. */}
      <Route path="/mentions-legales" element={<MentionsLegalesPage />} />
      <Route path="/cgu" element={<CguPage />} />
      <Route path="/accessibilite" element={<AccessibilitePage />} />

      <Route
        element={
          <RequireAuth>
            <Layout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="profile" element={<ProfilePage />} />
        {/* Modules gardés par rôle (PRD §2.5) : masqués de la nav ET de l'URL. */}
        <Route
          path="invoices"
          element={
            <RequireAccess permission="view-billing-section">
              <Lazy>
                <InvoicesPage />
              </Lazy>
            </RequireAccess>
          }
        />
        {/* C12.4 — Documents (internes à valider + administratifs) */}
        <Route path="documents" element={<DocumentsPage />} />
        <Route
          path="bookings"
          element={
            <RequireAccess permission="view-bookings-calendar">
              <Lazy>
                <BookingsPage />
              </Lazy>
            </RequireAccess>
          }
        />
        {/* Actualités : aucune permission de module (cf. Layout) — l'inscription
            à un événement est gardée dans RsvpButton. */}
        <Route path="announcements" element={<AnnouncementsPage />} />
        <Route path="announcements/:id" element={<AnnouncementDetailPage />} />
        {/* Tickets & bureaux nomades : réservé à l'external (PRD §3.5.6/§3.5.9,
            même garde que le back — DeskOccupationPolicy::viewAny). */}
        <Route
          path="tickets"
          element={
            <RequireAccess permission="create-paid-booking">
              <TicketsPage />
            </RequireAccess>
          }
        />
        <Route
          path="presence"
          element={
            <RequireAccess requiresDesk>
              <PresencePage />
            </RequireAccess>
          }
        />
        {/* C12.5 — Annuaire des coworkers + plan des étages */}
        <Route
          path="directory"
          element={
            <RequireAccess permission="view-annuaire">
              <Lazy>
                <DirectoryPage />
              </Lazy>
            </RequireAccess>
          }
        />
        <Route
          path="directory/plan"
          element={
            <RequireAccess permission="view-annuaire">
              <Lazy>
                <FloorPlanPage />
              </Lazy>
            </RequireAccess>
          }
        />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

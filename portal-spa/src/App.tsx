import { Route, Routes } from 'react-router'
import { Layout } from '@/components/Layout'
import { NotFound } from '@/components/NotFound'
import { AnnouncementDetailPage } from '@/features/announcements/AnnouncementDetailPage'
import { AnnouncementsPage } from '@/features/announcements/AnnouncementsPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAccess } from '@/features/auth/RequireAccess'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { BookingsPage } from '@/features/bookings/BookingsPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { DirectoryPage } from '@/features/directory/DirectoryPage'
import { FloorPlanPage } from '@/features/directory/FloorPlanPage'
import { DocumentsPage } from '@/features/documents/DocumentsPage'
import { InvoicesPage } from '@/features/invoices/InvoicesPage'
import { PresencePage } from '@/features/presence/PresencePage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { TicketsPage } from '@/features/tickets/TicketsPage'

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      {/* R-03 — cible du lien « mot de passe oublié » (PRD §3.2), hors auth */}
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />

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
              <InvoicesPage />
            </RequireAccess>
          }
        />
        {/* C12.4 — Documents (internes à valider + administratifs) */}
        <Route path="documents" element={<DocumentsPage />} />
        <Route
          path="bookings"
          element={
            <RequireAccess permission="view-bookings-calendar">
              <BookingsPage />
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
              <DirectoryPage />
            </RequireAccess>
          }
        />
        <Route
          path="directory/plan"
          element={
            <RequireAccess permission="view-annuaire">
              <FloorPlanPage />
            </RequireAccess>
          }
        />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}
